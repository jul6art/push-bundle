# ADR-0001 — Le point d'entrée du jeton d'abonnement Mercure

- **Statut** : Accepted — 2026-11-12
- **Décideurs** : jul6art
- **Origine** : la remontée dans les bundles du code que le squelette recopiait dans chaque application (deuxième de six lots)

## Contexte

Le bundle porte déjà tout ce qui fabrique un abonnement : `SubscriberCookieFactory` (`createToken`,
`subscription`, `createCookie`), `TopicPrefix` (le coin de l'application sur un hub partagé, 3.2) et les
résolveurs de sujets de publication. Il ne porte pas le **point d'entrée** que le navigateur appelle
(`<meta name="mercure-token-url">`, rendu par admin-bundle depuis `admin.mercure.token_route`, lu par
le bus de datatable-bundle) : chaque application l'écrit elle-même, en recopiant le
`MercureTokenController` du squelette.

Mesure du 2026-11-12 (`superp/docs/analyse/audit_fable_5_2_t20_copies.md`) : **10 copies, 10
variantes** — aucune identique à une autre. devinthecode n'a pas de copie : son `RealtimeController`
(`/api/realtime`) est un point d'API écrit pour son client Angular.

Toutes les copies font la même chose, en quatre temps :

1. choisir les sujets (et, depuis Mercure 1.0, les motifs) que le compte connecté a le droit d'écouter ;
2. frapper le jeton (`createToken`) ;
3. le poser en cookie (`createCookie`) — jamais dans le JSON : un hub 1.0 le refuse partout où du
   JavaScript le lirait ;
4. rendre `{ subscribed, [patterns] }`, préfixés comme le hub les transporte (`subscription`).

Les temps 2 à 4 sont recopiés à l'identique (aux commentaires près). **Le temps 1 est le produit** :
c'est une décision d'autorisation — un abonné reçoit tout ce qui passe sur un canal qu'il a le droit
d'écouter, et aucun filtre Doctrine ne s'applique à un canal.

### Les écarts, un par un

| Où | Écart | Classement |
|---|---|---|
| devinthehood, wtg, devingame, squelette | un seul sujet, `GlobalFeedTopicResolver::TOPIC` ; commentaires traduits ; type de retour `Response`/`JsonResponse` | dérive de style : abandonnée. Le sujet global devient le **défaut du bundle** |
| cegeta | le sujet de l'organisation du compte, `/platform/feed` pour le compte de plateforme (`OrganizationFeedTopicResolver`) | **besoin du produit** : gardé, par le point d'extension |
| cereezer | fil de l'espace (ou `PLATFORM` pour un super-administrateur) + fil personnel ; un **motif** « toutes les organisations » pour la plateforme | **besoin du produit** : gardé, par le point d'extension |
| tradebot | accès par `RealtimeVoter::SUBSCRIBE` ; sujets = canaux des permissions de lecture détenues (`FeedTopics::grantedBy`) | **besoin du produit** : accès configurable + point d'extension |
| devinlive | accès `IS_AUTHENTICATED` (« Se souvenir de moi » coché par défaut, ADR-0014 de devinlive) ; notifications, galeries, écrans par permission, certains en motifs ; classe non `final` | accès configurable + point d'extension ; `final` : dérive |
| superp, `/admin/mercure-token` (`app_mercure_token`) | super-administrateur seul ; `*`, devenu le motif `/superp/*` | **besoin du produit** (deux espaces) : voir § 4 |
| superp, `/organization/mercure-token` (`app_organization_mercure_token`) | `ROLE_USER` ; notifications, fil global, fil de l'organisation, flotte si la permission est détenue ; le JSON porte aussi `hubUrl` | **besoin du produit** (deux espaces) : voir § 4. ⚠️ **Défaut constaté** : voir § 5 |
| toutes | accès `IS_AUTHENTICATED_FULLY` (sauf devinlive, tradebot, superp) | devient le **défaut du bundle**, configurable |

## Décision

### 1. Le bundle livre le point d'entrée, sous `Mercure/` et `Controller/`

- **`Mercure\Subscription`** — valeur immuable : `topics` et `patterns` (listes de chaînes), tels que
  l'application les écrit, sans préfixe.
- **`Mercure\SubscriptionProviderInterface`** — le point d'extension :
  `subscriptionFor(UserInterface $user): Subscription`. C'est LA décision d'autorisation du produit.
  Une implémentation peut lever `AccessDeniedException` (refus explicite).
- **`Mercure\GlobalFeedSubscriptionProvider`** — le défaut : `GlobalFeedTopicResolver::TOPIC`, rien
  d'autre (le comportement du squelette, d'une application mono-locataire).
- **`Mercure\SubscriberTokenResponder`** — les temps 2 à 4 :
  `respond(Subscription $subscription, array $extra = []): JsonResponse` frappe le jeton, pose le
  cookie, rend `{ subscribed, patterns }` préfixés, plus les clés de `$extra`. Une application dont le
  point d'entrée a une autre forme (§ 4) l'appelle elle-même.
- **`Controller\MercureTokenController`** — contrôle d'accès (attribut configuré, ci-dessous), compte
  connecté, fournisseur configuré, répondeur. **Le bundle ne déclare aucune route** : l'application
  la déclare dans `config/routes/`, avec le nom et le chemin qu'elle a aujourd'hui
  (`admin_mercure_token`, `/admin/mercure-token`) — admin-bundle continue de la nommer par
  `admin.mercure.token_route`, rien ne change côté front.

### 2. Configuration

```yaml
push:
    mercure:
        subscriber:
            provider: App\Mercure\SubscriptionProvider   # défaut : GlobalFeedSubscriptionProvider
            access: IS_AUTHENTICATED_FULLY               # défaut ; tout attribut de isGranted()
```

Le contrôleur et le répondeur sont enregistrés avec `SubscriberCookieFactory` et retirés avec lui
par `MercureHubPass` quand l'application n'a pas de hub. Ils demandent le composant de sécurité de
Symfony : `symfony/security-bundle` passe en `suggest`, et ils ne sont enregistrés que s'il est là
(patron d'`AuthExtension`, `class_exists`).

La réponse porte **toujours** `patterns` (liste vide quand il n'y en a pas). Le bus de
datatable-bundle le lit déjà ainsi (`_addPatterns` ignore ce qui n'est pas une liste) : pour une
application qui n'en rendait pas, rien ne change.

### 3. Ce qui reste dans chaque application

| Application | Après le lot |
|---|---|
| devinthehood, wtg, devingame, squelette | la route seule, dans `config/routes/` : le contrôleur et sa classe disparaissent |
| cegeta, cereezer | la route + leur fournisseur (`App\Mercure\…SubscriptionProvider`, la décision d'aujourd'hui, déplacée telle quelle) |
| tradebot | la route + son fournisseur + `access: REALTIME_SUBSCRIBE` (l'attribut de son `RealtimeVoter`) |
| devinlive | la route + son fournisseur + `access: IS_AUTHENTICATED` |
| superp | voir § 4 |

### 4. superp : deux espaces, deux points d'entrée

superp a deux espaces avec deux points d'entrée, deux accès et deux formes de réponse (`hubUrl` dans
l'espace organisation). Un seul fournisseur par application ne les sert pas. Proposition : **ses
deux contrôleurs restent**, réduits au temps 1 — chacun construit sa `Subscription` et appelle
`SubscriberTokenResponder` (avec `extra: { hubUrl }` côté organisation). Écarté : faire porter à la
route le nom de son fournisseur et son accès (`defaults:`), une mécanique générale pour une seule
application, et une règle d'accès moins visible qu'un attribut sur le contrôleur.

devinthecode ne change pas : son `/api/realtime` rend `{ hub, topics }` à son client Angular, et
n'appelle `SubscriberCookieFactory` qu'en trois lignes. Le remplacer changerait le contrat de son API
pour un gain nul.

### 5. Défaut constaté dans superp (à corriger dans le lot)

L'espace organisation accorde et écoute `vehicle/{orgId}/{vehicleId}` — un **modèle d'URI**. Mercure
1.0 n'en connaît plus : un sujet est comparé tel quel, un joker passe par un motif
(`match_urlpattern`). Le serveur publie `vehicle/<org>/<véhicule>` : le jeton ne l'accorde pas et
`fleet_controller.js` ne l'écoute pas (il n'ajoute que des `match`). Sauf erreur de lecture, la carte
de flotte ne reçoit plus aucune position en direct depuis le passage à Mercure 1.0 (`260d83b43`,
2026-10-30). Correction : un **motif** `vehicle/<org>/*` dans `patterns`, et la carte qui écoute les
motifs comme le bus des tableaux. Prouvé d'abord par un test rouge, puis dans Chrome (une position
envoyée déplace le marqueur).

## Tests exigés (aucune modification sans eux)

- **Bundle** : tests unitaires (`Subscription`, fournisseur par défaut, répondeur : cookie — nom,
  chemin, `Secure`, `HttpOnly`, `SameSite=Strict` —, sujets et motifs préfixés, clés `extra`, jeton
  qui accorde exactement la liste rendue) ; tests fonctionnels sur un noyau de test avec la sécurité (anonyme refusé, attribut
  d'accès refusé → 403, fournisseur de l'application pris par la configuration, `AccessDeniedException`
  d'un fournisseur → 403) ; extension : clés par défaut, contrôleur absent sans hub et sans le
  composant de sécurité ; dépendances hautes et basses ; PHPStan, Rector, style.
- **Chaque application** : ses tests existants du point d'entrée, joués **avant et après** —
  cegeta `MercureTopicPartitioningTest`, cereezer `SubscriptionTopicsTest`, tradebot
  `FeedTopicsTest`, devinlive `LiveRefreshTest`, superp `MercureTokenControllerTest` ; un test
  ajouté là où il n'y en a pas (devinthehood, wtg, devingame : cookie posé, `subscribed` = le fil
  global préfixé, anonyme refusé) ; porte complète. **Avant et après, dans Chrome** : sur un tableau,
  l'appel du jeton (statut, `subscribed`, `patterns`, cookie) et le flux ouvert sur le hub (mêmes
  `match` / `match_urlpattern`) ; et, là où la pile Mercure locale tourne, une écriture faite dans un
  second onglet rafraîchit le tableau du premier.
- **Squelette** : contrôleur retiré de `modes/backoffice/overlay-user`, route ajoutée ; les cinq modes
  générés, leur porte jouée ; le parcours Chrome sur un backoffice généré.

## Conséquences

- push-bundle 3.3.0 (version mineure, badge). Les applications montent leur plancher.
- Déploiement des démonstrations d'abord (superp, cereezer, tradebot, wtg, devinthehood), puis des
  applications à vrais utilisateurs (devingame, cegeta, devinlive).
- Une application dont le point d'entrée a une autre forme garde son contrôleur et s'appuie sur
  `SubscriberTokenResponder` ; le contrat (`subscribed`, `patterns`, cookie) ne change pas.
