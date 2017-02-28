vagrant ssh 95b681d -c "/usr/local/bin/wp db import /srv/www/wordpress-default/public_html/edd-dbfs-db.sql --path=/srv/www/wordpress-default/public_html"

rem Commands used to update plugins and save changes to the DB
rem vagrant ssh 95b681d -c "/usr/local/bin/wp plugin update --all --path=/srv/www/wordpress-default/public_html"
rem vagrant ssh 95b681d -c "/usr/local/bin/wp db import /srv/www/wordpress-default/public_html/edd-dbfs-db.sql --path=/srv/www/wordpress-default/public_html"