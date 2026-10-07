# Test technique Blissim

Deux parties : un schéma SQL avec deux requêtes (`blissim.sql`), et une petite application Symfony qui affiche des
produits venant de l'API [dummyjson](https://dummyjson.com/products) et permet de commenter chaque produit.

## Prérequis

- Docker avec Docker Compose. Rien d'autre à installer : PHP 8.4, MySQL 8.4 et Composer tournent dans les conteneurs.
- `make` (facultatif) pour les raccourcis ci-dessous.

## Lancer l'application

```bash
make up
```

Sans `make` : `docker compose --env-file .env -f docker/docker-compose.yml up -d --build`.

Au premier démarrage, Composer installe les dépendances (environ une minute, `make logs` pour suivre), puis
l'application répond sur http://localhost:8080. L'application tourne en environnement `dev` : une erreur affiche la page
de debug de Symfony, les pages d'erreur du site sont visibles sur `/_error/404` et `/_error/503`.
Si un port est déjà pris : `make up APP_PORT=8081 DB_PORT=3309` (application et base).

- `/` : liste des produits (titre, image, prix)
- `/products/{id}` : fiche produit, liste des commentaires, ajout
- `/comment/{id}/edit` et `/comment/{id}/delete` : modification et suppression d'un commentaire

Les autres commandes (`make help` pour la liste) : `make down`, `make logs`, `make shell`, `make console c="debug:router"`,
`make db-shell`, `make db-reset`, `make test`, `make stan`.

## Base de données

MySQL 8.4 est lancé par le compose. Au premier démarrage, `blissim.sql` est exécuté automatiquement : il crée les tables
(`customers`, `products`, `orders`, `order_items`, `comments`), insère un jeu de données et exécute les deux requêtes
demandées. Leurs résultats s'affichent en rejouant le fichier (commande ci-dessous).

| Paramètre | Valeur |
|---|---|
| Hôte (depuis l'hôte) | `127.0.0.1`, port `3308` (`DB_PORT` pour le changer) |
| Base | `blissim` |
| Utilisateur / mot de passe | `app` / `app` (de développement uniquement) |

Rejouer le fichier SQL : `make db-reset` (supprime le volume et recrée la base), ou à la main, sans toucher au volume
(le fichier supprime d'abord les tables, **les commentaires existants sont donc perdus**) :

```bash
make db-shell < blissim.sql
```

### Partie SQL

- `unit_price` est stocké dans `order_items` : c'est le prix payé à la commande, il ne change pas si le prix du produit évolue.
- Requête 1 : clients ayant commandé le produit 1, sans doublon (`DISTINCT`).
- Requête 2 : quantités vendues par produit sur les 7 derniers jours, décroissantes. « 7 derniers jours » est lu comme
  une fenêtre glissante (`NOW() - INTERVAL 7 DAY`) et non comme 7 jours calendaires.
- Les dates du jeu de données sont relatives à `NOW()` pour que la requête 2 renvoie toujours des lignes.
- Pas de clé étrangère sur `comments.product_id` : le produit vit dans l'API externe, pas dans la base.

## Tests et analyse statique

```bash
make test   # PHPUnit
make stan   # PHPStan, niveau 6
```

Trois niveaux de tests :

- **Unitaire** : `ProductApiClient` avec `MockHttpClient` (liste, produit, 404, 500, JSON invalide, panne réseau). Aucun appel réseau.
- **Intégration** : `CommentRepository` sur la vraie base du compose. Chaque test s'exécute dans une transaction annulée à
  la fin, les données ne sont pas modifiées.
- **Fonctionnel** : parcours complet d'un commentaire (création, 303, affichage, validation 422, 404, jeton CSRF).
  L'API produits est remplacée par `tests/Support/FakeProductApi`. Ce test écrit dans la vraie base et supprime ses lignes
  (auteur `PHPUnit`) à la fin.

## Organisation du code

```
src/Controller/   ProductController (liste, fiche, ajout), CommentController (modification, suppression)
src/Form/         CommentType (champs et contraintes du formulaire)
src/Service/      ProductApiClient et ses exceptions
src/Repository/   CommentRepository (seul endroit avec du SQL)
src/Model/        Product, Comment (objets immuables)
src/EventListener ExceptionListener (exceptions métier -> 404 / 503)
templates/        pages Twig et pages d'erreur
tests/            unitaires, intégration, fonctionnels
```

## Choix de conception

- **Symfony 7.4, sans Doctrine.** Routage, formulaires, validation, CSRF, Twig et client HTTP sont des briques solides que
  je ne vois pas de raison de réécrire. L'énoncé demande PDO : pas d'ORM, le SQL reste lisible dans un seul repository.
- **PDO déclaré comme service** (`config/services.yaml`) : DSN construit depuis les variables d'environnement
  (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`), `ERRMODE_EXCEPTION`, `EMULATE_PREPARES` désactivé, timeout 3 s.
  `CommentRepository` le reçoit par autowiring et n'écrit que des requêtes préparées à paramètres nommés.
- **Couches** : `Controller` (HTTP) → `Service` (client de l'API) / `Repository` (PDO). Le contrôleur ne contient aucun SQL.
- **Client API avec `HttpClient`** : timeout d'inactivité de 3 s et durée totale de 10 s, pas de redirection. Un 404 devient
  `ProductNotFoundException`, toute autre panne (réseau, statut inattendu, JSON invalide) une `ProductApiException`.
- **Formulaire Symfony** (`CommentType`) : champs nettoyés (trim), contraintes `NotBlank` et `Length` (100 / 1000), jeton
  CSRF automatique. Le formulaire travaille sur un tableau, sans entité. En cas d'erreur, la page est ré-affichée en 422.
- **Post/Redirect/Get** : un ajout ou une modification réussie redirige en 303.
- **Suppression en `POST` uniquement**, protégée par un jeton CSRF propre à chaque commentaire (403 sinon).
- **Erreurs gérées à un seul endroit** (`ExceptionListener`) : produit inconnu → 404, API ou base indisponible → 503. Les
  pages sont rendues par Twig, le détail technique reste dans les logs. Le repository est injecté dans l'action, pas
  dans le constructeur : si MySQL tombe, la liste des produits continue de fonctionner.
- **Twig** échappe toute sortie : un commentaire contenant du HTML s'affiche comme du texte.

## Limites connues et pistes

- Pas d'authentification : n'importe qui peut modifier ou supprimer n'importe quel commentaire.
- Pas de pagination (30 produits chargés) ni de cache de l'API : chaque page appelle dummyjson.
- Configuration de développement uniquement : le `APP_SECRET` de `.env.dev` et les identifiants `app` / `app` sont publics.
  En production, il faut les définir hors du dépôt (variables d'environnement ou secrets Symfony).
- Le test d'intégration du repository est ignoré si la base est absente ; le test fonctionnel, lui, a besoin de la base du compose.
