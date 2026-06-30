/**
 * Formatea un número como monto con 2 decimales (estilo en-US: 1,234.50).
 */
export const formatMoney = (n) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(n) || 0);
