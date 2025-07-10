# Meili Kitchen AI search

Implementation is

https://www.meilisearch.com/docs/learn/ai_powered_search/getting_started_with_ai_search#choose-an-embedder-name

Also, dummyjson projects.  Other than showing how to embed documents, though, it's not integrated with anything else.

Instead, use dt-demo or dummy with an embedder.

```bash
git clone git@github.com:survos-sites/kitchen && cd kitchen
composer install
```

Add the keys for meili and open ai

```
#.env.local
MEILI_API_KEY=
MEILI_SERVER=http://127.0.0.1:7700/
OPENAI_API_KEY=
```

Load the products

```bash
bin/console import:products
bin/console import:kitchen-data
symfony server:start -d
symfony open:local --path=/search
```
