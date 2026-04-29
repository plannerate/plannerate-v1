import type { Ref } from 'vue'
import { unref } from 'vue'

/**
 * Composable para navegação em arrays
 * Fornece funções helper para encontrar elementos anterior, próximo, primeiro, último
 */

export function useArrayNavigation<T>(items: T[] | Ref<T[]>) {
  /**
   * Retorna o elemento anterior na lista
   */
  const getPrevious = (item: T): T | undefined => {
    const itemsArray = unref(items)
    const index = itemsArray.indexOf(item)

    if (index <= 0) {
return undefined
}

    return itemsArray[index - 1]
  }

  /**
   * Retorna o próximo elemento na lista
   */
  const getNext = (item: T): T | undefined => {
    const itemsArray = unref(items)
    const index = itemsArray.indexOf(item)

    if (index === -1 || index >= itemsArray.length - 1) {
return undefined
}

    return itemsArray[index + 1]
  }

  /**
   * Retorna o primeiro elemento da lista
   */
  const getFirst = (): T | undefined => {
    const itemsArray = unref(items)

    if (!itemsArray.length) {
return undefined
}

    return itemsArray[0]
  }

  /**
   * Retorna o último elemento da lista
   */
  const getLast = (): T | undefined => {
    const itemsArray = unref(items)

    if (!itemsArray.length) {
return undefined
}

    return itemsArray[itemsArray.length - 1]
  }

  /**
   * Verifica se é o último elemento
   */
  const isLast = (item: T): boolean => {
    const itemsArray = unref(items)

    return itemsArray.indexOf(item) === itemsArray.length - 1
  }

  /**
   * Verifica se é o primeiro elemento
   */
  const isFirst = (item: T): boolean => {
    const itemsArray = unref(items)

    return itemsArray.indexOf(item) === 0
  }

  /**
   * Retorna o índice do elemento
   */
  const getIndex = (item: T): number => {
    const itemsArray = unref(items)

    return itemsArray.indexOf(item)
  }

  return {
    getPrevious,
    getNext,
    getFirst,
    getLast,
    isLast,
    isFirst,
    getIndex,
  }
}
