import ProductDetailsController from './ProductDetailsController'
import ProductImageController from './ProductImageController'

const Api = {
    ProductDetailsController: Object.assign(ProductDetailsController, ProductDetailsController),
    ProductImageController: Object.assign(ProductImageController, ProductImageController),
}

export default Api