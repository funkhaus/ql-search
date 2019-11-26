# QL Search
[![Build Status](https://travis-ci.org/funkhaus/ql-search.svg?branch=master)](https://travis-ci.org/funkhaus/ql-search)

## What is QL Search?
An extension that intergrates [SearchWP](https://searchwp.com) into [WPGraphQL](https://www.wpgraphql.com).

## Quick Install
1. Install & activate [SearchWP](https://searchwp.com)
2. Install & activate [WPGraphQL](https://www.wpgraphql.com)
3. Clone or download the zip of this repository into your WordPress plugin directory & activate the plugin.

## Features
- Search across multiple post-types.
- Filter taxonomy, meta, date, and more...

## Some Examples
### Query multiple types with a single `input`.
```
query {
    searchWP(first: 5, where: { input: "Hello World" }) {
        nodes {
            ... on Post {
                id
            }
            ... on Page {
                id
            }
        }
    }
}
```
The `input` parameter is the base search field, and request on all `searchWP` queries.

### Query with an alternative `engine`.
```
query {
    searchWP(first: 5, where: { input: "Hello World", engine: "other-engine-slug" }) {
        nodes {
            ... on Post {
                id
                ... more post fields
            }
            ... on Page {
                id
                ... more page fields
            }
        }
    }
}
```
The `engine` parameter by default is set to *default*. An important thing to remember is that in order for a post-type to be returned as an `searchWP` result be enable on the **SearchWP** engine, Find out more about SearchWP's engine configuration [here](https://searchwp.com/docs/configuration/), and it must have `exclude_from_search` set to `false` and `show_in_graphql` set to `true` in it's **Post-type** configurations. 

### Query by `taxonomies`.
```
query {
    searchWP(first: 5, where: { input: "Hello World", taxonomies: { taxArray: [{ taxonomy: TAG, field: SLUG, terms: "test_tag" }] } }) {
        nodes {
            ... on Post {
                id
                ... more post fields
            }
            ... on Page {
                id
                ... more page fields
            }
        }
    }
}
```
The `taxonomies` parameter is designed to be identical to the enhanced `taxQuery` parameter used by **[WPGraphQL Tax Query](https://github.com/wp-graphql/wp-graphql-tax-query)**. Another important thing to remember about **SearchWP** and by relation **QL Search**, is that in order to query a specific taxonomy, that taxonomy must be given a weight on the *engine* being used. In the cause of the query above that is the default engine. Find out more about SearchWP's engine configuration [here](https://searchwp.com/docs/configuration/).

### Query by `meta`.
```
query {
    searchWP(first: 5, where: { input: "Hello World", meta: { metaArray: [{ key: "test_meta", value: "meta value", compare: EQUAL_TO }] } }) {
        nodes {
            ... on Post {
                id
                ... more post fields
            }
            ... on Page {
                id
                ... more page fields
            }
        }
    }
}
```
The `meta` parameter is designed to be identical to the enhanced `metaQuery` parameter used by **[WPGraphQL Meta Query](https://github.com/wp-graphql/wp-graphql-meta-query)**.

### Query by `date`.
```
query {
    searchWP(first: 5, where: { input: "Hello World", date: [{ year: 1970, month: 1, day: 1 }] }) {
        nodes {
            ... on Post {
                id
                ... more post fields
            }
            ... on Page {
                id
                ... more page fields
            }
        }
    }
}
```
The `date` parameter is designed to be identical to the `date` parameter on the core [WPGraphQL](https://github.com/wp-graphql/wp-graphql) post object connection.
