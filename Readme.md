Google OAuth2 Sample Application
================================

About
-----

Just a simple manual implementation of Googles OAuth2 server authentication 
flow. Deliverately not using any helper libraries to study the necessary HTTP
interactions in detail.

Built mostly using [Slim Framework](http://www.slimframework.com/) and 
[Guzzle HTTP](http://guzzle.readthedocs.org/en/latest/)

Intallation/Usage
-----------------

### Preconditions

 * You must obtain a client_id and client_secret to access Google OAuth2 API,
   as described in section "Register your app" of 
   [Google OAuth2 Documentation](https://developers.google.com/accounts/docs/OAuth2Login)

### Using Vagrant

 * Clone or download repo\
 * Copy app/config.dist.php to app/config.php, add client_id and client_secret
 * Start vagrant box inside /vagrant folder
 * SSH into Vagrant box, cd /var/www/google-oauth, composer install
 * Open https://localhost:4430/

### Manual install in local server
 
 * Neither tested, not supported, guess you know what you are doing
 * Deploy on your local Apache2
 * Server MUST use HTTPS for this or the auth callbacks won't work
 * Modify config.dist.php as decribed above
 * Set auth.callback.url to match your local IP


License/ & Copyright
--------------------

MIT License (see LICENSE)

Copyright (c) 2014 Joerg Henning

