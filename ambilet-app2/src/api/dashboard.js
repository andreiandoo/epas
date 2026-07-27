import { apiGet } from './client';

export async function getDashboard(params = {}) {
  return apiGet('/organizer/dashboard', params);
}

export async function getSalesTimeline(params = {}) {
  return apiGet('/organizer/dashboard/sales-timeline', params);
}

export async function getRecentOrders(limit = 10) {
  return apiGet('/organizer/dashboard/recent-orders', { limit });
}
