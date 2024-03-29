# Change Log
All notable changes to this project will be documented in this file.

## [Unreleased]

## Version 1.11

- FIX : DA021763 - Prise en compte de la date d'inventaire pour le caulcul des inventaires *2022-04-26* - 1.11.7
- FIX : Bug affichage du select -Choix du filtre de catégorie- dans un nouvel inventaire *2022-01-11* - 1.11.6
- FIX : Fatal error and return value for save method *2021-09-22* - 1.11.5
- FIX : V13 Compatibility no token renewal *2021-05-07* - 1.11.4
- FIX : PHP error for non countable element *2021-03-09*
- FIX : Sort inventory's details on p.ref ASC by default *2021-03-09* - 1.11.2
- FIX : V13 Compatibility action links - 1.11.1
- NEW : Ajout de l'objet inventory pour fonctionnement correct des hooks pour export CSV et ODT - 1.11.0
- NEW : hooks on list && hooks on CSV and ODT exports - 1.10.0

## Version 1.9

### Added

- NEW : Sort lines per product
- NEW : Evol product's extrafields column in inventory card
- NEW : Add column "lot" in inventory's ODT
- NEW : Create CSV of an inventory with batch's number
- NEW : Multiple categ

### Changed

- FIX : Remove unused Box - 1.9.1
- FIX : Getposts in modules
- FIX : Tk11498 - new option => huge performance boost by replacing/caching getNomURL()
- FIX : Minus doesn't change total quantity
- FIX : Tk9573 : arrondi pour éviter les valeurs de stock à 1.4210854715202E-14
- FIX : Fatal error on Dolibarr <= 6.0
