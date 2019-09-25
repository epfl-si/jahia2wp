# Shortcode Polylex

Ce shortcode permet un interfaçage avec l'application [wp-polylex](https://github.com/epfl-idevelop/wp-polylex).

Fonctionnalités :
- UI permettant
    -  l'affichage de la liste complète
    -  navigation par catégorie ou sous catégorie, au travers de boîtes déroulantes
    -  recherche textuelle de lexes, sur :
        -  le numéro
        -  le titre
        -  la description

- Préfiltrage paramétrable

## Utilisation

Inclure cette ligne dans la page
```
[epfl_polylex_search]
```

###  Préfiltrage

S'il est souhaité de filter la liste affichée à l'utilisateur, deux options sont fournis :

#### par URL

En fournissant une URL avec le filtre en paramètre.

Exemples :

https://mon-site.com/page-avec-le-shortcode?category=Gouvernance

https://mon-site.com/page-avec-le-shortcode?subcategory=Assistants-%C3%A9tudiants

https://mon-site.com/page-avec-le-shortcode?search=Loi%20f%C3%A9d%C3%A9rale%20sur%20les%20%C3%A9coles%20polytechniques%20f%C3%A9d%C3%A9rales


#### par configuration de l'appel au shortcode

En éditant la page, en modifiant l'appel au shortcode en y ajoutant les paramètres à filtrer.

Exemples :
```
[epfl_polylex_search category="Gouvernance"]
```

```
[epfl_polylex_search subcategory="Assistants-étudiants"]
```

```
[epfl_polylex_search search="Loi fédérale sur les écoles polytechniques fédérales"]
```
