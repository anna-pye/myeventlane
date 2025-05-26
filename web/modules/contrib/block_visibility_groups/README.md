# Block Visibility Groups

Block Visibility Groups module allows the site administrator to easily manage
complex visibility settings that apply to any block placed in a visibility
group. The visibility settings for all blocks in the group can be edited on one
administration form.

This module works in conjunction with modern Drupal (8, 9, 10) core's block
administration system. It is a simpler alternative to modules like
[Panels](https://www.drupal.org/project/panels).

What to see how it works?
[View a demo](https://www.youtube.com/watch?v=ZKwkaMUbJIs)

For a full description of the module, visit the
[project page](https://www.drupal.org/project/block_visibility_groups).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/block_visibility_groups).


## Requirements

This module requires no modules outside of Drupal core.


## Recommended modules

The following modules provide additional conditions that can be used with
Block Visibility Groups:

1. [CTools](https://www.drupal.org/project/ctools) Provides various conditions
   including bundle for each entity type.
1. [Menu Condition](https://www.drupal.org/project/menu_condition) provides a
   condition based on menu position. For example, you can use it to specify
   that a block should only show for a particular menu item and all its
   children.
1. [Term Condition](https://www.drupal.org/project/term_condition) provides a
   simple Condition plugin which checks to see if the current node has a
   specific taxonomy term.
1. [Token Conditions](https://www.drupal.org/project/token_conditions) creates
   a simple Token matching condition plugin.
1. [Vocabulary Condition](https://github.com/md-systems/vocabulary_condition)
   provides a vocabulary visibility condition. See also
   [#2281659: Create a vocabulary condition.](https://www.drupal.org/node/2281659)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Go to Administration » Structure » Block Layout » Block Visibility Group.
1. Click on "Add Block Visibility Group" button.
1. Add label and select other options according requirement.
1. Add conditions, If condition section is not appearing then save the group
   and edit again.
1. Now configure the block which you want show using 'Block Visibility Group'.
1. Choose the conditional group from Visibility section from configured block.


## Maintainers

- Damien McKenna - [damienmckenna](https://www.drupal.org/u/damienmckenna)
- Joël Pittet - [joelpittet](https://www.drupal.org/u/joelpittet)
- Benjamin Melançon - [mlncn](https://www.drupal.org/u/mlncn)
- Ted Bowman - [tedbow](https://www.drupal.org/u/tedbow)
