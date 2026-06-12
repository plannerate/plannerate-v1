import editor from './editor'
import products from './products'
import plannerate from './plannerate'

const api = {
    editor: Object.assign(editor, editor),
    products: Object.assign(products, products),
    plannerate: Object.assign(plannerate, plannerate),
}

export default api