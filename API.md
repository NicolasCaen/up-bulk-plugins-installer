# API REST des sets

## Authentification
- Les requêtes doivent être effectuées avec un compte possédant la capacité `manage_options`.
- Utilisez un nonce WordPress REST en l’envoyant dans l’en-tête `X-WP-Nonce`.
- En environnement de test sécurisé, vous pouvez recourir à l’authentification basique.

## Namespace
Toutes les routes sont exposées via `up-bulk-plugins-installer/v1`.

## Endpoints
- `GET /wp-json/up-bulk-plugins-installer/v1/sets`
  - Retourne un tableau de sets sauvegardés (`slug`, `name`, `items`, `meta`).
- `POST /wp-json/up-bulk-plugins-installer/v1/sets/<slug>/install`
  - Lance l’installation du set correspondant au `slug`.
  - Paramètre JSON optionnel : `manifest_target` (`theme`, `mu-plugins`, `plugins`) pour forcer la destination.

## Exemples `curl`
```bash
curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets \
  -H "X-WP-Nonce: <nonce>"

curl https://example.com/wp-json/up-bulk-plugins-installer/v1/sets/mu-plugins-cpt/install \
  -X POST \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{"manifest_target":"mu-plugins"}'
```

## Réponses
- **GET**: tableau JSON listant chaque set complet.
- **POST**: objet JSON indiquant le nombre d’éléments traités, la destination effective et les messages d’exécution.

## Conseils
- Générez le nonce côté WordPress via `wp_create_nonce('wp_rest')`.
- Vérifiez que le `slug` transmis correspond exactement au fichier JSON de set.
- Utilisez `manifest_target` seulement si vous souhaitez outrepasser la destination par défaut définie dans le set.
