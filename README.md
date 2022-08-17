# Entity Overview #

## Introduction

Entity Overview is a module designed to easily give editors and developers tools to make lists and overviews of entities with built in support for Ajax and deep links. Maximum customizability for editors and maximum reusability for developers.

## Configuration

One of the main components of the overviews is an overview configuration. Each configuration selects what entities are filtered and what fields and data on those entities are used as facets.

A search engine is also selected for the configuration and depending upon the engine other facets and features may be available.

Currently, you are limited to a configuration with just a single entity type with bundle, but future development might allow multiple entity types in a single configuration for the engines that support it. Also for facets only taxonomy fields are supported and a single date field for sorting.

## Engines

The engines of Entity Overview does the actual searching and filtering of entities and returns a result. These can have different feature sets like free text search or support for multiple entity types and bundles.

Normally any results of the engines are transformed into entities, but it is possible to get the raw result should this be advantageous for performance or additional information.

Current engines are _Entity Query_ and _Relewise_ through the _Relewise_ module.

## Fields

For site builders overviews are provided as a field type called _Overview filter_ which utilizes an overview configuration to specify a filter. The field also supports exposing facets to end users if editors so choose.

A standard field widget is also provided for selecting filter options and exposed facets if the field allows it.

Last but not least two field formatters are provided. One that simply returns the filtered entities in a given view mode and nothing more. Great for displaying the 5 latest articles.
The other provides a form for exposed facets and allows the end user to refine filter options on the fly and uses the editor selected filter options as just a default selection.

These field plugins are also intended to be expandable in case custom solutions are needed and field formatters can be made to display entities in any way we could imagine.

## Forms

The form overview formatter utilizes a form called _OverviewFilterForm_ which is very customizable. It is possible to extend it and programaticly change facets, how the entities are queried, how the entities are displayed. You can use the form for overviews without any overview configuration or use of an engine.
