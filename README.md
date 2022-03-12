# Public storage
Simple snippet hosting service, like pastebin but for APIs.

Take a look at [api.http](api.http) for docs.

Currently, hosted at [ps.cout970.net](https://ps.cout970.net)

### Features
- API REST interface
- Minimal dependencies/low project complexity (just need php and php-sqlite)
- Fast and simple
- Secure, follows the recommended security practices
- Free and open source

### How to host
- Install dependencies php8.1 and php8.1-sqlite (on ubuntu `sudo add-apt-repository ppa:ondrej/php -y` is required for php8.1)
- Make sure pdo_sqlite extension is not commented in `/etc/php/php.ini` or `/etc/php/8.1/cli/php.ini` or `/etc/php/8.1/fpm/php.ini`
- Clone the repo with git `git clone https://github.com/cout970/public_storage`
- Enter the project folder `cd public_storage`
- Download composer2 (follow the [official guide](https://getcomposer.org/download/))
- Install php dependencies `php ./composer.phar install`
- Update perms 
  - `mkdir data`
  - `chown root:www-data -R .` 
  - `find . -type d -exec chmod 755 {} \;`
  - `find . -type f -exec chmod 644 {} \;`
  - `chmod 775 data`
- Enter the public directory `cd public`
- Run the php test server `php -S localhost:8080`
- Test that the API is working as expected and stop the process
- Configure a reverse proxy (nginx/caddy/apache2) to add HTTPS support
- Example with nginx:
  - Install nginx (depends on OS `sudo apt install nginx`, `pacman -S nginx`, etc.)
  - Create a config file in `/etc/nginx/sites-available` with the following contents:
```conf
server {
    server_name <server domain>;
    root <path to public_storage>/public;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ '\.php$' {
        fastcgi_split_path_info ^(.+?\.php)(|/.*)$;
        # Ensure the php file exists. Mitigates CVE-2019-11043
        try_files $fastcgi_script_name =404;

        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_index index.php;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }
}
```
  - Create a symlink in sites-enabled `ln -s /etc/nginx/sites-available/domain /etc/nginx/sites-enabled/domain`
  - Check the syntax is correct `nginx -t`
  - Restart nginx
  - Check that the web returns a 200 OK
  - Use certbot to enable https `certbot --nginx`
  - Restart nginx

### Other project
- [nss.cout970.net](https://nss.cout970.net)