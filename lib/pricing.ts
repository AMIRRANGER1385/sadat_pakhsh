export function unitPrice(p: {retail:number; wholesale:number; minimum:number}, quantity:number, approved=false) { return approved || quantity >= p.minimum ? p.wholesale : p.retail; }
export function shippingCost(subtotal:number, settings:{shipping:number;freeAbove:number}) { return settings.freeAbove > 0 && subtotal >= settings.freeAbove ? 0 : settings.shipping; }
export const money = (n:number) => new Intl.NumberFormat('fa-IR').format(n);
