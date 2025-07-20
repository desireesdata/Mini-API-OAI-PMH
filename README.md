# CSV2OAI : Serveur OAI-PMH pour fichiers CSV et EAD

Ce projet implémente un serveur OAI-PMH (_Open Archives Initiative Protocol for Metadata Harvesting_) simple, en **PHP**. Il expose des métadonnées provenant de deux sources : un fichier **CSV** (au format _Dublin Core Element Set_) et des fichiers **XML/EAD** (Encoded Archival Description).

> **Note pédagogique :** Ce projet a été développé à des fins éducatives pour illustrer le fonctionnement du protocole OAI-PMH, notamment la gestion des verbes, des formats de métadonnées (oai_dc et EAD), des sets, de la pagination via les resumptionTokens, et les défis liés au moissonnage de données à grande échelle. Il sert de support pour comprendre les interactions entre un entrepôt OAI-PMH et un moissonneur.

> Note : code écrit avec l'aide du LLM [Mistral-7B-Instruct-v0.3](https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.3) sur Prompt personnel.
> Note 2: il a été modifié avec Gemini.

---

## Prérequis

- **PHP ≥ 7.2** (aucune extension spéciale requise, le but est d'être le plus simple possible et de dépendre le moins possible des exisgences du serveur qui l'hébergera)
- Serveur HTTP (Apache, Nginx, ou intégré via `php -S`)
- Fichier CSV structuré selon le format _Dublin Core Element Set_
- Accès au serveur via URL (localhost ou en ligne)

---

## Fichiers du projet

| Fichier          | Description |
|------------------|-------------|
| `oai-pmh.php`    | Point d’entrée principal du serveur OAI-PMH |
| `utils.php`      | Fonctions PHP auxiliaires pour charger et accéder aux données |
| `data.csv`       | Base de données CSV avec les enregistrements Dublin Core |
| `ead/`           | Dossier contenant les fichiers XML/EAD moissonnés |
| `harvest.py`     | Script Python pour moissonner les données OAI-PMH (ex: FranceArchives) |
| `index.html`     | Interface de test OAI-PMH (facultative) |

---

## Installation

1. Clone ou copie les fichiers dans ton serveur web local :

   ```bash
   git clone https://example.com/oai-php.git
   cd oai-php/
   ```

2. Lance un serveur local PHP (si besoin) :

   ```bash
   php -S localhost:8000
   ```

3. Accède à :

   ```
   http://localhost:8000/oai-pmh.php?verb=Identify
   ```

---

## Fonctionnement

Le script supporte les verbes suivants du protocole OAI-PMH :

- `Identify`
- `ListMetadataFormats`
- `ListSets`
- `ListIdentifiers`
- `ListRecords`
- `GetRecord`

Le verbe est passé par URL via `?verb=...`.

---

## Format du CSV attendu

Le fichier `data.csv` doit contenir une première ligne avec les champs suivants (en anglais, sans accents) :

```
set;identifier;title;creator;subject;description;publisher;date;type;format;language;coverage;rights;relation
```

- set : est le marqueur pour le Set de l'OAI-PMH et est utilisé dans le verbe `ListSets`.
- Les autres champs correspondent aux chamsp du _Dublin Core Element Set_.

---

## Gestion des fichiers XML/EAD

En plus du fichier CSV, ce serveur OAI-PMH est capable d'exposer des instruments de recherche au format **EAD (Encoded Archival Description)**. Ces fichiers doivent être placés dans le dossier `ead/`.

Le serveur parcourt récursivement ce dossier. Chaque sous-dossier direct de `ead/` est traité comme un `set` OAI-PMH. Par exemple, un fichier `ead/serie_Fi/mon_ir.xml` sera associé au set `serie_Fi`.

Lors d'une requête `GetRecord` avec `metadataPrefix=ead`, le serveur renvoie le contenu XML complet du fichier EAD correspondant.

---

## Détail des fichiers et fonctions

### `oai-pmh.php`

Ce fichier reçoit les requêtes OAI-PMH et génère une réponse XML conforme au protocole.

#### Variables importantes :

- `$verb` — Verbe OAI demandé (`Identify`, `ListRecords`, etc.)
- `$resumptionToken` — Index de pagination pour les listes
- `$batchSize` — Nombre d’enregistrements par réponse (modifiable, ex. `10`)

#### Logique principale :

```php
switch ($verb) {
  case 'Identify':
    // Retourne les métadonnées du dépôt
  case 'ListIdentifiers':
    // Liste uniquement les identifiants et datestamps
  case 'ListSets':
    // Liste uniquement les sets
  case 'ListRecords':
    // Retourne les enregistrements Dublin Core complets
  case 'GetRecord':
    // Retourne un enregistrement à partir de son identifiant
}
```

#### XML généré :

- Conforme à OAI-PMH 2.0
- Utilise le schéma Dublin Core (`oai_dc`)

---

### `utils.php`

Contient les fonctions de traitement du fichier CSV.

#### `load_records($filename = 'data.csv')`

- Charge les enregistrements du fichier CSV
- Nettoie les entêtes
- Assure un identifiant et une date valide pour chaque ligne

#### `get_record_by_id($identifier, $records)`

- Recherche un enregistrement dans le tableau par identifiant OAI

#### `validate_date($date)`

- Vérifie si une date est au format `YYYY`, `YYYY-MM`, ou `YYYY-MM-DD`

---

## Exemples d'URL de test

| Verbe            | Exemple d’URL |
|------------------|-----------------------------|
| Identify         | `?verb=Identify` |
| ListIdentifiers  | `?verb=ListIdentifiers&metadataPrefix=oai_dc` |
| ListSets         | `?verb=ListSets` |
| ListRecords      | `?verb=ListRecords&metadataPrefix=oai_dc` |
| GetRecord        | `?verb=GetRecord&identifier=oai:example:1&metadataPrefix=oai_dc` |
| Pagination       | `?verb=ListRecords&metadataPrefix=oai_dc&resumptionToken=10` |

---

## Script de moissonnage (moisson.py)

Pour illustrer le processus de moissonnage des données OAI-PMH, un script Python nommé `harvest.py` est inclus dans ce projet. Ce script est conçu pour : 

1.  **Collecter tous les identifiants** d'un `set` donné (par exemple, un service d'archives sur FranceArchives.gouv.fr) en gérant la pagination via les `resumptionToken`.
2.  **Télécharger chaque document XML/EAD** correspondant à ces identifiants.

### Utilisation

1.  **Prérequis :** Assurez-vous d'avoir Python 3 et la bibliothèque `requests` installés (`pip install requests`).
2.  **Configuration :** Modifiez les variables `OAI_BASE_URL`, `SET_TO_HARVEST`, et `METADATA_PREFIX` dans le script `harvest.py` pour cibler l'entrepôt et le set souhaités.
3.  **Exécution :** Lancez le script depuis votre terminal :
    ```bash
    python3 harvest.py
    ```

Le script créera un dossier `harvest_NOM_DU_SET/` et y sauvegardera tous les fichiers XML/EAD moissonnés.

---

## Notes et limitations

- Les données sont intégralement extraites depuis `data.csv`.
- La pagination se fait via `resumptionToken`.
- Le script n'implémente pas les fonctionalités de `deleted`, `from`, `until` de l'OAI dans la mesure où il doit rester très léger pour les utilisateurs non spécialiste de l'OAI. Pour celles et ceux qui souhaitent une intégration complète du protocole OAI-PMH, d'autres outils sont disponibles avec une gestion plus fine (Dataverse, Omeka Classic ou S, etc.)

---

## FAQ

**Q : Le script ne retourne qu’un seul enregistrement. Pourquoi ?**  
A : Vérifiez que le paramètre `$batchSize` dans `oai-pmh.php` est bien défini à 10 (ou le nombre voulu).

**Q : Le XML est vide ou ne contient pas de données ?**  
A : Assurez-vous que le fichier `data.csv` est encodé en UTF-8 sans BOM, avec `;` comme séparateur, et que les entêtes sont exacts.

---

## Licence et citation

Ce projet est open-source, voir le fichier LICENSE pour plus d'information.

Citation : POUYLLAU, S. (CNRS), FERAL, J. with Mistral 7b and Gemini, _CSV2OAI : Serveur OAI-PMH pour fichier CSV_, juillet 2025.

---

## Contact

Créé par Stéphane Pouyllau, ingénieur de recherche CNRS. 

Modifié par J. FERAL.

Date : juillet 2025.