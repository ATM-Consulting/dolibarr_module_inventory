# Change Log
All notable changes to this project will be documented in this file.

## [Unreleased]

- NEW : hooks on list
- NEW : hooks on CSV and ODT exports
- FIX : PHP error for non countable element *2021-03-09*

## Version 1.11.2

### Fix 

- V13 Compatibility action links

## Version 1.11.1

### Fix 

- Ajout de l'objet inventory pour fonctionnement correct des hooks pour export CSV et ODT

## Version 1.9.1

### Fix 

- Remove unused Box


## Version 1.9

### Added

- NEW : Sort lines per product
- NEW : Evol product's extrafields column in inventory card
- NEW : Add column "lot" in inventory's ODT
- NEW : Create CSV of an inventory with batch's number
- NEW : Multiple categ

### Changed

- FIX : Getposts in modules
- FIX : Tk11498 - new option => huge performance boost by replacing/caching getNomURL()
- FIX : Minus doesn't change total quantity
- FIX : Tk9573 : arrondi pour éviter les valeurs de stock à 1.4210854715202E-14
- FIX : Fatal error on Dolibarr <= 6.0
