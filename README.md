API.KNIGOPIS.COM
=================
REST API


Deployment
------------------
1. Download composer.phar to project root https://getcomposer.org/composer.phar
2. Install plugin `php composer.phar global require "fxp/composer-asset-plugin:~1.1.1"` (may be unnecessary)
3. Run `php composer.phar install`
4. Copy `config/params.php` to `config/params_private.php` and edit it.
5. Set write permissions on _runtime_ dir

Development
------------------
You need a auth token from uLogin. Get it from js console at www.knigopis.com or your dev www (try to login)  
When you get _auth token_, get _access token_ from API `GET /user/get-credentials?token=auth_token`.  
Insert it in every request when **[user is required]**.  
You can pass it with GET-param as `access-token=token_string`
or add to header as `Authorization: Bearer token_string`  
  
**UPDATE**: Now API sets auth cookie after successful request to `GET /user/get-credentials?token=auth_token`  
and you do not need store and send access-token every request (less REST but more secure, because cookie is http only). 

Pay attention to trailing slash. If you make request `GET /books/` it will return `404`. You need `GET /books`.  

Implemented
------------------
`GET /user/get-credentials?token=auth_token` - get access token by uLogin auth token for futher requests, also returns details of the user  
`POST /users/get-credentials` - get access token by uLogin auth token (as above) passed in POST (token=auth_token). More secure method.  
`GET /users` - get user info who is owner of access token (_is not REST, should not be used_) **[user is required]**  
`GET /users/current` - get user info who is owner of access token **[user is required]**  
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
`POST /wishes` - create new wush record (pass values in POST) **[user is required]**  
`PUT /wishes/:id` - update wish **[user is required]**  
`DELETE /wishes/:id` - delete wish by wish id **[user is required]**  

Tools and help
------------------
http://www.yiiframework.com/doc-2.0/guide-rest-quick-start.html - examples of requests with cUrl,  
Try addon https://addons.mozilla.org/ru/firefox/addon/httprequester/ for making POST, PUT, DELETE requests.  


License
------------------
The MIT License
