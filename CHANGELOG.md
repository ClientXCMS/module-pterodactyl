# Changelog

Toutes les évolutions notables du module Pterodactyl sont consignées ici.
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions : [Semantic Versioning](https://semver.org/lang/fr/).

## [1.5.0] - 2026-10-31

### Ajouté
- Compatibilité avec Pterodactyl 2.0, en plus de la 1.x : le module détecte seul la version de chaque panel et utilise les eggs et les tags en 2.0, les nests en 1.x.
- La configuration produit affiche les eggs d'un panel 2.0 avec leurs tags, par exemple « Mumble Server (Voice Servers) ».
- Après la mise à jour d'un panel de 1.x vers 2.0, le module bascule de lui-même dans les 10 minutes ; vider le cache de ClientXCMS le fait basculer tout de suite.

### Corrigé
- Les configurations produit et les services enregistrés avant le passage du panel en 2.0 continuent de fonctionner, sans ressaisie, et les eggs déjà choisis restent sélectionnés dans la configuration produit.
- Les listes d'utilisateurs, de serveurs et d'eggs sont lues en entier, 100 éléments par page : un client est retrouvé même sur un panel de plus de 100 comptes ou serveurs, et son mot de passe n'est plus réinitialisé à tort.
- Une adresse e-mail contenant un « + » retrouve bien son compte Pterodactyl.
- Un panel injoignable ou qui refuse la clé n'empêche plus d'afficher les eggs et les emplacements des autres panels.
- Une liste d'eggs vide (panel momentanément en erreur) n'est plus gardée en cache.
- Le changement d'egg depuis l'espace client n'accepte plus que les eggs proposés par l'offre, et une réinstallation sans changement garde la commande de démarrage du serveur.

## [1.4.0]

Version publiée avant l'ouverture de ce journal (numéro `1.4` dans `module.json`, normalisé en `1.4.0`).
