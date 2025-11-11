## Introduction

The Modeler API provides an API for modules like
[ECA - Events, Conditions, Actions](https://www.drupal.org/project/eca),
[Migrate Visualize](https://www.drupal.org/project/migrate_visualize),
[AI Agents](https://www.drupal.org/project/ai_agents),
and maybe others. The purpose is to allow those modules to utilize modelers like
[BPMN.iO](https://www.drupal.org/project/bpmn_io),
(and maybe others in the future) to build diagrams constructed of their
components (e.g. plugins) and write them back into module-specific config
entities.

## Modeler API Architecture

<img src="/files/Modeler_api%20diagram.png" alt="Modeler API Diagram" />

The modules with complex configuration entities (e.g. ECA, Migrate, AI Agents,
etc.) are called **Model Owners**.

The modules providing the UI to configure those complex configuration entities
are called **Modelers**.

The **Modeler API** sits right in between the model owners and the modelers. It
makes sure that each of the model owners can interact with each of the
modelers. However, the model owners and the modelers don't know each other, not
even a tiny bit. And that makes this eco-system very strong. Users will even be
able to edit each of the complex configuration entities (a.k.a. models) with
each of the modelers without losing any of the important configurations.

### Provided infrastructure by Modeler API

Integrating a new model owner or a new modeler into the Modeler API should be
straightforward as the Modeler API provides plugin interfaces for both sides.
But there's even more, that model owners gain:

- Routing: Modeler API creates all the required routes on the fly
- Permissions: Modeler API controls access on all routes
- UI: Modeler API provides the overview page with operations, settings, etc.
- Useful feature like import, export, export as recipe, etc.
- Logging: will provide a logging infrastructure for the model owners
- Replay: will provide a UI in modelers to replay processing with logged data
- Storage of raw modeler data

All this is provided by the Modeler API for all model owners and for all
modelers, i.e. for the combinations of them. Example: if you have 4 model
owners and 3 modelers, there will be 12 combinations, that are all managed
out-of-the-box, free of any manual configuration or setup.

### Plans for more modelers

The well-known BPMN.iO modeler from the ECA eco-system has been integrated
into Modeler API as a starting point. We also hope that the Classic Modeler,
which is fairly simple and form-based, but therefore fully accessible, will
also be integrated, see [#3522747].

More potential modelers are being discussed, too, and with this Modeler API
the effort is fairly minimal, so that we hope that more developers will show
up and build more exciting modelers. We're happy to provide guidance, and the
known ideas include but are not limited to these:

- [n8n](https://n8n.io): as this is not Open-Source, it can still be similar
- UML: there are various flavours of UML around, e.g. PlantUML
- React-based (or other JS-framework) fancy UIs
- Maybe even Drupal's Experience Builder

## Benefits

Why are we doing all this?

<div class="note-version">

#### This is great for end-users

Using diagraming tools is something users like. It's intuitive and it helps
breaking complex tasks into pieces.

Imagine, Drupal end-users can use one and the same UI to manage not only one
of their complex workflows but all of them. They only learn that UI once, and
re-use where ever this is being integrated.

Another step in flattening the learning curve for new Drupal users.

</div>

<div class="messages warning">

#### This is also great for developers

Building the UI for ECA has been an effort. Building something similar for all
the other use cases where Drupal manages complex configuration is certainly not
less complex. Building similar UIs more than once is not very smart.

Joining forces and maintaining the Modeler API together, allowing other
developers focusing on either model owners or modelers, is the best possible
structure for effective teamwork.

</div>
