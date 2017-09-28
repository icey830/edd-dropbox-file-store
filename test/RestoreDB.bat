C:\xampp\xampp_stop.exe
C:\xampp\xampp_start.exe
cp "C:\Freelance\edd-dropbox-file-store-plugin\plugin\test\edd-dbfs-db.sql" "C:\xampp\htdocs\edd\wordpress.sql"
C:\xampp\mysql\bin\mysql.exe -u admin -ppassword wordpress < wordpress.sql


rem Commands used to update plugins and save changes to the DB
rem vagrant ssh 00a6b5e -c "/usr/local/bin/wp db import /srv/www/wordpress-default/public_html/edd-dbfs-db.sql --path=/srv/www/wordpress-default/public_html"
rem vagrant ssh 00a6b5e -c "/usr/local/bin/wp plugin update --all --path=/srv/www/wordpress-default/public_html"
rem vagrant ssh 00a6b5e -c "/usr/local/bin/wp db import /srv/www/wordpress-default/public_html/edd-dbfs-db.sql --path=/srv/www/wordpress-default/public_html"