PHPN2J_Gateway
==============

Standalone N2J Gateway "NNTP to JNTP" for Inn

Usage :
-------
Copy this file and rename it wathever you want.
Better is to copy it in path_to_inn/bin and keep the name (in FreeBSD: /usr/local/news/bin/n2j_gateway.php).
Set the executable bit : chmod +x /usr/local/news/bin/n2j_gateway.php
Configure your newsfeeds file, for example :
JNTP!/from-jntp:[group pattern]:Tp,Wf:/usr/local/news/bin/n2j_gateway.php %s [server]

Manualy : /usr/local/news/bin/n2j_gateway.php [token] [server]

You can know the token of an Usenet article with the command :
/usr/local/news/bin/grephistory '<Message-ID>'

You can test it with the command :
./n2j_gateway.php --test
