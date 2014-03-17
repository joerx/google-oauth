<?php

$config = require(dirname(__FILE__).'/config.php');

$app = new \Slim\Slim(array_merge($config, array(
  'templates.path'       => './app/templates',
  'log.writer'           => new \Slim\LogWriter(fopen('./app/log/application.log','a')),
)));

$app->add(new GoogleOAuth\Slim\SessionMiddleWare());

/**
 * Index page handler. Presents a link to the user consent page
 */
$app->get('/', function() use ($app) {

  if (!isset($_SESSION['auth.access-token'])) {

    $app->log->debug("No access token, redirecting to Google Consent page");
    
    $state_token = md5(uniqid());
    $_SESSION['auth.state'] = $state_token;

    $data = array(
        'client_id'     => $app->config('google.client.id'),
        'response_type' => 'code',
        'scope'         => 'openid email https://www.googleapis.com/auth/youtube.readonly',
        'state'         => $state_token,
        'redirect_uri'  => $app->config('auth.callback.url')
    );

    $auth_url = sprintf('%s?%s', $app->config('google.auth.url'), http_build_query($data));
    $app->response->redirect($auth_url);
    
  } else {
    
    $app->log->debug("Access token found, loading application");

    $client = new Guzzle\Http\Client();
    $request = $client->get('https://www.googleapis.com/plus/v1/people/me', null, array('exceptions' => false));
    $request->setHeader('Authorization', sprintf('Bearer %s', $_SESSION['auth.access-token']));

    $response = $request->send();
    $body = $response->json();
    
    if ($response->getStatusCode() != 200) {
      $app->response->header('Content-type', 'text/plain');
      print_r($body);
      throw new Exception("Error retrieving profile information");
    }
    
    $displayName = $body['displayName'];
    $email = $body['emails'][0]['value'];
    
    $_SESSION['user.display-name'] = $displayName;
    $_SESSION['user.email'] = $email;
    
    $app->render('index.php', array('displayName' => $displayName, 'email' => $email));
  }
});

/**
 * Retrieves some sample data from Youtube data API
 */
$app->get('/youtube/data', function() use ($app) {
  
  if (!$_SESSION['auth.access-token']) {
    throw new Exception('No access token found');
  }
  
  $query = array(
      'part' => 'id,snippet,status',
      'mine' => 'true',
  );
  
  $client = new Guzzle\Http\Client($app->config('youtube.data-api.url'));
  $ytRequest = $client->get(sprintf('%s?%s', '/youtube/v3/playlists', http_build_query($query)), null, array('exceptions' => false));
  $ytRequest->setHeader('Authorization', sprintf('Bearer %s', $_SESSION['auth.access-token']));
  
  $ytResponse = $ytRequest->send();
   
  if ($ytResponse->getStatusCode() != 200) {
    throw new Exception(sprintf('Error fetching data from Youtube: %s', $ytResponse->getBody(true)));
  }
  
  $data = $ytResponse->json();
  $responseData = array_map(function($item) {
    return array(
        'id' => $item['id'],
        'title' => $item['snippet']['title'],
        'thumbnail' => $item['snippet']['thumbnails']['default']['url'],
        'status' => $item['status']['privacyStatus']
    );
  }, $data['items']);
  
  $app->response->header('Content-type', 'application/json');
  $app->response->write(json_encode($responseData));
  
});

/**
 * Callback handler invoked by Google OAuth API after the user gracefully accepted our authentication request.
 */
$app->get('/auth/callback', function() use ($app) {

  // If auth.state is not present the url was likely invoked directly
  if (!isset($_SESSION['auth.state'])) {
    $app->log->error('No auth state in session. Session data: {ssn}', array('ssn' => json_encode($_SESSION)));
    throw new Exception('No state found in SESSION, something is wrong!');
  } else {
    $app->log->debug('Auth state: {state}', array('state' => $_SESSION['auth.state']));
  }

  // Verify auth state token to make sure we don't have the NSA in the middle
  if ($_SESSION['auth.state'] != $app->request->params('state')) {
    $app->log->warn(sprintf('Auth state in session: %s', $_SESSION['auth.state']));
    $app->log->warn(sprintf('Auth state in request: %s', $app->request->params('state')));
    throw new Exception("Invalid request state. You may be under attack!");
  }

  // Don't do this at home!
  $app->log->debug(sprintf('Authorization code: %s', $app->request->params('code')));
  $app->log->debug('Fetching authorization token...');

  // Get an access token for the authorization code
  $client = new Guzzle\Http\Client();
  $data = array(
      'code' => $app->request->params('code'),
      'client_id' => $app->config('google.client.id'),
      'client_secret' => $app->config('google.client.secret'),
      'redirect_uri' => $app->config('auth.callback.url'),
      'grant_type' => 'authorization_code',
  );
  $options = array('exceptions' => false);

  $tokenRequest = $client->post($app->config('google.token.url'), null, $data, $options);
  $tokenResponse = $tokenRequest->send();
  $body = $tokenResponse->json();
  
  $app->log->debug(sprintf('Response from OAuth2 server: %s', $tokenResponse->getStatusCode()));
  $app->log->debug(sprintf('Token response: %s', json_encode($body)));

  if ($tokenResponse->getStatusCode() != 200) {
    throw new Exception(sprintf('Error getting token: %s', json_encode($body)));
  } else {
    $app->log->debug(sprintf('Received access token %s %s, expires in %s',
            $body['token_type'],
            $body['access_token'], 
            $body['expires_in']));
    
    $_SESSION['auth.access-token'] = $body['access_token'];
    $app->response->redirect('/');
  }
});

$app->run();