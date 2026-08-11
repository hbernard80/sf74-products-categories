# Symfony : entités, Doctrine etc.

> 📆 11/08/2026

Projet de démo Symfony avec entités produits et catégories.

## Stack technique

Démarrage à partir du clone du projet [sf74-bootstrap](https://github.com/hbernard80/sf74-bootstrap)

## ✅ Fait

* Entités _Product_ et _Category_.
* Relations :
  * Un produit peut appartenir à plusieurs catégories (_many-to-many_)
  * Catégorie et sous-catégories (relation réflexive)
* Utilisation du trait pour les dates de création et de modification. Lors d'un ajout, la date de modification est définie comme égale à celle de création
* Correction du problème "n+1" des relations Doctrine
* Génération de fixtures (avec Faker)
* Page d'accueil : affichage des 5 derniers produits et 5 dernières catégories par date de création descendante.
* Champ de recherche de produit et de catégorie sur les pages de liste
* Mise en oeuvre de la pagination (avec PagerFanta)  
* Va validation dans les formulaires
* Gestion des listes déroulantes catégorie parente/sous-catégories dans les formulaires : le choix d'uen catégorie parente peuple la seconde liste avec les sous-catégories associées (via JS/Stimulus, évènements et réponse JSON)

## ❌ Non implémenté

* Tests unitaires (sauf si générés automatiquement)
* Traductions (français en dur dans les templates sans utiliser le système de translations)
* Utilisateurs et sécurité
* Version responsive non optimisée (s'affiche comme Boostrap l'aura décidé 😲!)
