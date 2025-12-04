1. Comment ai-je identifié la cause du problème ?

J’ai vérifié trois points essentiels :

La migration (2024_01_01_000002_create_articles_table.php) créait la table en latin1_general_ci ❌

La configuration (config/database.php) indiquait pourtant une connexion MySQL en utf8_general_ci

La requête SQL utilisée auparavant était vulnérable et ne gérait pas correctement les accents

👉 Conclusion : le problème venait clairement du collationnement latin1_general_ci, qui ne permet pas les recherches insensibles aux accents.

2. Comment ai-je corrigé le problème sans toucher aux données ?

J’ai choisi une solution sûre : normaliser les chaînes côté PHP, sans modifier la base de données.

J’ai créé une fonction removeAccents() qui convertit par exemple « café » → « cafe ».

Lors des recherches, je compare la version normalisée du titre avec celle de la requête.

Les données originales en base restent intactes. Aucun risque de corruption ou de perte.

3. Comment ai-je validé que tout fonctionnait ?

J’ai effectué plusieurs tests :

Gestion des accents : « cafe » trouve « café », « ete » trouve « été » → OK

Majuscules : tout est converti en minuscule avant comparaison → OK

Caractères spéciaux : support de œ, æ, ñ, ç → OK

Sécurité : passage à Eloquent ORM → aucune injection SQL possible → OK