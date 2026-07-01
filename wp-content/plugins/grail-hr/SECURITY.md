## Sécurité production : répertoire privé

En environnement local, Grail HR utilise par défaut le répertoire suivant pour stocker les fichiers temporaires, exports et logs applicatifs :

```text
wp-content/grail-hr-private/
```

Ce répertoire contient des données techniques qui ne doivent pas être exposées publiquement. Le plugin ajoute des fichiers de protection pour les environnements Apache, mais une configuration serveur adaptée reste nécessaire en production.

Les fichiers `.htaccess` sont interprétés par Apache uniquement si `AllowOverride` est activé. Ils ne sont pas interprétés par nginx. En production nginx, une règle serveur explicite est donc nécessaire si le répertoire reste dans `wp-content`.

### Recommandation production

En production, l’accès HTTP au répertoire privé doit être bloqué explicitement au niveau du serveur web.

### Exemple nginx

```nginx
location ^~ /wp-content/grail-hr-private/ {
    deny all;
    return 403;
}
```

### Exemple Apache

```apache
<Directory "/var/www/html/wp-content/grail-hr-private">
    Require all denied
</Directory>
```

ou via `.htaccess` si `AllowOverride` est activé.

### À retenir

Les fichiers temporaires PDF sont nommés avec des identifiants non prédictibles et supprimés après extraction. Les logs filtrent les données sensibles avant écriture. Le blocage explicite du répertoire privé au niveau serveur reste requis pour garantir l’isolation complète des données en production.