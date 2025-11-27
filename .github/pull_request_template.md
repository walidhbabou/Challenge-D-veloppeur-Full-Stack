## [TICKET-ID] Titre du ticket

**Statut** : ✅ Résolu / ⏳ En cours / 🔶 Partiellement résolu

---

### Problème identifié

[Décrivez la cause racine du bug/problème en 2-4 phrases]

**Exemple** :
> La recherche ne fonctionne pas avec les accents car la collation de la table `articles` est configurée en `latin1_general_ci` au lieu de `utf8mb4_unicode_ci`. Les comparaisons de chaînes sont donc sensibles aux accents.

---

### Solution implémentée

[Décrivez votre correction avec détails techniques]

**Changements effectués** :
- [Liste des modifications]
- [Fichiers modifiés]
- [Configurations changées]

**Code clé (optionnel)** :
```php
// Exemple de code corrigé si pertinent
```

---

### Tests effectués

[Comment avez-vous vérifié que ça fonctionne ?]

**Exemple** :
- [ ] Recherche "cafe" trouve l'article "Le café du matin"
- [ ] Recherche "café" trouve aussi l'article
- [ ] Recherche "CAFE" fonctionne (insensible à la casse)

---

### Réponse aux questions à considérer

[Répondez aux questions posées dans TICKETS.md pour ce ticket]

**Question 1** : [Question copiée de TICKETS.md]
> [Votre réponse]

**Question 2** : [Question copiée de TICKETS.md]
> [Votre réponse]

---

### Temps passé

Environ X heures

---

### Difficultés rencontrées (optionnel)

[Mentionnez les blocages et comment vous les avez résolus]

---

### Checklist

- [ ] Code fonctionne localement
- [ ] Tests manuels passés
- [ ] Commits avec messages clairs
- [ ] Documentation mise à jour si nécessaire

