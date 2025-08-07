API.KNIGOPIS.COM
=================
REST API for knigopis.com - a service with books which you have read.

Deployment
------------------
1. Download composer.phar to project root https://getcomposer.org/composer.phar
2. Install plugin `php composer.phar global require "fxp/composer-asset-plugin:~1.1.1"` (may be unnecessary)
3. Run `php composer.phar install`
4. Copy `config/params.php` to `config/params_private.php` and edit it.
5. Set write permissions on _runtime_ dir

Deployment with the docker and docker-compose
----------------------
1. `cp docker-compose.dist.yml docker-compose.yml`
2. `sudo bash -c "chmod 777 ./runtime; chgrp 33 -R .; chmod g+rw -R ."`
3. `docker-compose up -d --build`
4. `docker-compose exec app composer install`

Replace the path with your location of the repo and run:
`sudo bash -c "chgrp 33 -R /var/repo.knigopis.com; chmod g+rw -R /var/repo.knigopis.com"`

Additional:
`touch YII_ENV_DEV` for debug

Development
------------------
You need an auth token from uLogin. Get it from js console at www.knigopis.com or your dev www (try to login)  
When you get _auth token_, get _access token_ from API `GET /user/get-credentials?token=auth_token`.  
Insert it in every request when **[user is required]**.  
You can pass it with GET-param as `access-token=token_string`
or add to header as `Authorization: Bearer token_string`  
  
**UPDATE**: Now API sets auth cookie after successful request to `GET /user/get-credentials?token=auth_token`  
and you do not need to store and send access-token every request (less REST but more secure, because cookie is http only). 

Pay attention to trailing slash. If you make request `GET /books/` it will return `404`. You need `GET /books`.  

Implemented
------------------
`POST /auth/register` - register new user, pass values _username_, _password_ and _lang_ in POST  
`POST /auth/login` - login user, pass values _username_, _password_ and _lang_ in POST  

`GET /user/get-credentials?token=auth_token` - get access token by uLogin auth token for further requests, also returns details of the user  
`POST /users/get-credentials` - get access token by uLogin auth token (as above) passed in POST (token=auth_token). More secure method.  
`GET /users` - get user info who is the owner of access token (_is not REST, should not be used_) **[user is required]**  
`GET /users/current` - get user info who is the owner of access token **[user is required]**  
`GET /users/:id` - get user info by user id  
`PUT /users/:id` - update user info, pass values _nickname_ and _profile_ in POST **[user is required]**  
`GET /users/:id/books` - get all books owned by userId  
`GET /users/latest` - get list of the latest updated users  
`GET /users/find-id-by-parse-id` - find new id by old id  
`POST /users/copy-books-from-user/:id` - copy books from specified user to current user **[user is required]**  
  
`GET /books` - get all books owned by user **[user is required]**  
`GET /books/:id` - get book by book id  
`POST /books` - create new book record (pass values in POST) **[user is required]**  
`PUT /books/:id` - update book **[user is required]**  
`DELETE /books/:id` - delete book by book id **[user is required]**  
`GET /books/latest` - get list of the latest added books  
`GET /books/latest-notes` - get list of the latest added books with notes only  

`GET /subscriptions` - get all subscriptions **[user is required]**  
`POST /subscriptions/:subUserId` - create a new subscription to subUserId **[user is required]**  
`PUT /subscriptions/:subUserId` - update a subscription to subUserId **[user is required]**  
`DELETE /subscriptions/:subUserId` - unsubscribe user from subUserId **[user is required]**  

`GET /wishes` - get wish list owned by user **[user is required]**  
`GET /wishes/:id` - get wish by wish id  
`POST /wishes` - create new wish record (pass values in POST) **[user is required]**  
`PUT /wishes/:id` - update wish **[user is required]**  
`DELETE /wishes/:id` - delete wish by wish id **[user is required]**  


License
------------------
The MIT License
