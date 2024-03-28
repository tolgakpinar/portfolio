Mettre le script à la racine 

Puis 

chmod 777 -R /Scripts
crontab -e (en root)
*/2 * * * * /usr/bin/php /Scripts/traitement.php > /Scripts/logs.txt

Verifier que l'extension curl est installée
sinon la telecharger

apt-get install php-curl