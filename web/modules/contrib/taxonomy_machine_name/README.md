TAXONOMY MACHINE NAME
---------------------

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Features
 * Installation
 * Configuration


INTRODUCTION
------------

Taxonomy is a very helpful tool in Drupal's world. However, support for
"machine_name" is really missing, overall when you need to exchange terms
with others systems. In such situation, the only way is to create a field
to hold external term code which is not the best way due to poor performance
consequences.

This module create a new property named "machine_name" as for Taxonomy
Vocabulary to store a "slug" for each term, and a "unique key" index to
check unicity into a vocabulary. If not provided during term creation,
machine name will be automatically generated based on term name.

This new property can be used too with pathauto to generates rewrited urls.


REQUIREMENTS
------------

 * Taxonomy


FEATURES
--------

 * Alter database schema to add new column for "taxonomy_term_data" table
 * Automatically generate machine_name if missing based on term name
 * Can use Pathauto, Transliteration or Token to sluggify term name
 * Add function to load one term by its machine name
 * Machine name exposed to Views
 * Machine name exposed to Token
 * Machine name exposed to Migrate (Destination & Field Handler)
 * Machine name exposed to Rules


INSTALLATION
------------

Taxonomy Machine Name can be installed like any other Drupal module

- Download the module file in drupal.org, place it in the modules directory.


CONFIGURATION
-------------

Navigate to:
Administration > Structure > Taxonomy,
and click the 'List terms' which taxonomy you want to edit,
then you can choose the item you want to change, the machine name edit link
is just after the name textfield.
