// Composable for assortment and product status analysis based on the provided VBA logic

export interface Product {
  category: string;
  quantity: number;
  value: number;
  margin: number;
  currentStock: number;
  lastPurchase: string | Date | null;
  lastSale: string | Date | null;
  [key: string]: any; // For extra fields
}

export interface Weights {
  quantity: number;
  value: number;
  margin: number;
}

export interface Thresholds {
  a: number;
  b: number;
}

export interface ProductAnalysis extends Product {
  weightedAverage: number;
  individualPercent: number;
  accumulatedPercent: number;
  abcClass: 'A' | 'B' | 'C';
  ranking: number;
  classRank: string;
  removeFromMix: boolean;
  status: string;
  statusDetail: string;
}

export function useAssortmentStatus(
  products: Product[],
  weights: Weights,
  thresholds: Thresholds,
  today: Date = new Date()
): ProductAnalysis[] {
  // Step 1: Calculate weighted average for each product
  const analyzed: ProductAnalysis[] = products.map((product) => {
    const quantity = Number(product.quantity) || 0;
    const value = Number(product.value) || 0;
    const margin = Number(product.margin) || 0;

    let sumWeights = 0;
    let weightedSum = 0;

    if (quantity !== 0) {
      sumWeights += weights.quantity;
      weightedSum += quantity * weights.quantity;
    }
    if (value !== 0) {
      sumWeights += weights.value;
      weightedSum += value * weights.value;
    }
    if (margin !== 0) {
      sumWeights += weights.margin;
      weightedSum += margin * weights.margin;
    }

    const weightedAverage = sumWeights !== 0 ? +(weightedSum / sumWeights).toFixed(6) : 0;

    return {
      ...product,
      weightedAverage,
      individualPercent: 0,
      accumulatedPercent: 0,
      abcClass: 'C',
      ranking: 0,
      classRank: '',
      removeFromMix: false,
      status: '',
      statusDetail: '',
    };
  });

  // Step 2: Sort by category (asc) and weightedAverage (desc)
  analyzed.sort((a, b) => {
    if (a.category < b.category) return -1;
    if (a.category > b.category) return 1;
    // Dentro da categoria, ordenar por média ponderada decrescente
    return b.weightedAverage - a.weightedAverage;
  });

  // Step 3: Calculate individual and accumulated percent for each category
  let currentCategory = '';
  let totalWeighted = 0;
  let accumulated = 0;
  for (let i = 0; i < analyzed.length; i++) {
    const prod = analyzed[i];
    if (prod.category !== currentCategory) {
      currentCategory = prod.category;
      totalWeighted = analyzed.filter(p => p.category === currentCategory)
        .reduce((sum, p) => sum + p.weightedAverage, 0);
      accumulated = 0;
    }
    const individualPercent = totalWeighted !== 0 ? prod.weightedAverage / totalWeighted : 0;
    accumulated += individualPercent;
    prod.individualPercent = individualPercent;
    prod.accumulatedPercent = accumulated;
  }

  // Step 4: ABC Classification
  for (let i = 0; i < analyzed.length; i++) {
    const prod = analyzed[i];
    if (prod.accumulatedPercent <= 0.8) {
      prod.abcClass = 'A';
    } else if (prod.accumulatedPercent <= 0.85) {
      prod.abcClass = 'B';
    } else {
      prod.abcClass = 'C';
    }
  }

  // Step 5: Ranking and Class+Rank
  let lastCategory = '';
  let rank = 1;
  for (let i = 0; i < analyzed.length; i++) {
    const prod = analyzed[i];
    if (prod.category !== lastCategory) {
      lastCategory = prod.category;
      rank = 1;
    }
    prod.ranking = rank;
    prod.classRank = prod.abcClass + String(rank);
    rank++;
  }

  // Step 6: Remove from Mix (for C class with individualPercent < half of lowest B in category)
  let currentCat = '';
  let minBPercent = 1;
  for (let i = 0; i < analyzed.length; i++) {
    const prod = analyzed[i];
    if (prod.category !== currentCat) {
      currentCat = prod.category;
      minBPercent = 1;
      for (let j = 0; j < analyzed.length; j++) {
        const p = analyzed[j];
        if (p.category === currentCat && p.abcClass === 'B') {
          if (p.individualPercent < minBPercent) minBPercent = p.individualPercent;
        }
      }
    }
    if (prod.abcClass === 'C' && prod.individualPercent < minBPercent / 2) {
      prod.removeFromMix = true;
    } else {
      prod.removeFromMix = false;
    }
  }

  // Step 7: Product Status and Detail
  for (let i = 0; i < analyzed.length; i++) {
    const prod = analyzed[i];
    const stock = Number(prod.currentStock) || 0;
    const lastPurchase = prod.lastPurchase ? new Date(prod.lastPurchase) : null;
    const lastSale = prod.lastSale ? new Date(prod.lastSale) : null;

    let status = '';
    let statusDetail = '';

    const daysSincePurchase = lastPurchase ? Math.floor((today.getTime() - lastPurchase.getTime()) / (1000 * 60 * 60 * 24)) : null;
    const daysSinceSale = lastSale ? Math.floor((today.getTime() - lastSale.getTime()) / (1000 * 60 * 60 * 24)) : null;

    if (
      lastSale && daysSinceSale !== null && daysSinceSale <= 120 &&
      lastPurchase && daysSincePurchase !== null && daysSincePurchase <= 120
    ) {
      status = 'Ativo';
      statusDetail = 'Venda e compra recentes';
    } else if (
      lastSale && daysSinceSale !== null && daysSinceSale <= 120 &&
      (!lastPurchase || daysSincePurchase === null || daysSincePurchase > 120)
    ) {
      status = 'Ativo';
      statusDetail = 'Venda recente, sem compra';
    } else if (
      lastPurchase && daysSincePurchase !== null && daysSincePurchase <= 120 &&
      (!lastSale || daysSinceSale === null || daysSinceSale > 120)
    ) {
      status = 'Ativo';
      statusDetail = 'Compra recente, sem venda';
    } else {
      statusDetail = 'Sem venda e sem compra';
      if (stock === 0) {
        status = 'Inativo';
      } else {
        status = 'Ativo';
      }
    }
    prod.status = status;
    prod.statusDetail = statusDetail;
  } 
  // Próximas etapas virão aqui
  return analyzed;
} 