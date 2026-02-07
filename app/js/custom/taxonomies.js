/**
 * =============================================================================
 * LEGACY CODE - Global Variables
 * =============================================================================
 * This file manages taxonomy data (categories, tags) using global variables.
 * 
 * MIGRATION STATUS: Not yet migrated
 * DEPENDENCIES: Global variables (categories, tags, taxonomies)
 * USED BY: Pages that filter/display content by taxonomy
 * 
 * TO REMOVE: When taxonomy handling is refactored to use modern patterns
 * (e.g., ES modules, state management), this file can be removed.
 * =============================================================================
 */

function setChildCategories(data) {
    for (var i = 0; i < data.length; i++) {
        categories[data[i].id] = data[i]
    }
    // console.log('categories', categories)

    return data
}

function setCategories(data) {
    // console.log("categories json", data)
    if (data != null) {
        for (var i = 0; i < data.length; i++) { //creates object of categories by key
            categories[data[i].id] = data[i]
        }
    }
    //  console.log('categories', categories)

    return data
}

function setTaxonomy(data, tax) {
    taxonomies[tax] = {}
    if (data[tax] != null) {
        for (var i = 0; i < data[tax].length; i++) { //creates object of categories by key
            taxonomies[tax][data[tax][i].id] = data[tax][i]
        }
    }
    // console.log(tax, taxonomies[tax])

    return data
}

function setTags(data) {
    if (!data || !Array.isArray(data)) {
     //   console.log('setTags: data is not an array or is undefined');
        return data;
    }
    for (var i = 0; i < data.length; i++) {
        tags[data[i].id] = data[i]
    }
    //console.log('tags', tags)

    return data
}