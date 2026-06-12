import gondolas from './gondolas'
import segments from './segments'
import categories from './categories'
import sections from './sections'
import planograms from './planograms'
import shelves from './shelves'
import layers from './layers'
import products from './products'

const editor = {
    gondolas: Object.assign(gondolas, gondolas),
    segments: Object.assign(segments, segments),
    categories: Object.assign(categories, categories),
    sections: Object.assign(sections, sections),
    planograms: Object.assign(planograms, planograms),
    shelves: Object.assign(shelves, shelves),
    layers: Object.assign(layers, layers),
    products: Object.assign(products, products),
}

export default editor