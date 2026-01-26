/**
 * PromotionsDashboard Page
 * Main dashboard for organizer promotions
 */

import React, { useEffect } from 'react';
import { usePromotions, usePromotionOrder } from '../hooks';
import { PromotionTypeCard } from '../components';
import { OrderStatus } from '../types';

interface PromotionsDashboardProps {
  onCreatePromotion: () => void;
  onViewOrder: (orderId: number) => void;
}

export const PromotionsDashboard: React.FC<PromotionsDashboardProps> = ({
  onCreatePromotion,
  onViewOrder,
}) => {
  const { promotionTypes, isLoading: typesLoading } = usePromotions();
  const {
    orders,
    statistics,
    isLoading: ordersLoading,
    fetchOrders,
    fetchStatistics,
  } = usePromotionOrder();

  useEffect(() => {
    fetchOrders({ limit: 5 });
    fetchStatistics();
  }, [fetchOrders, fetchStatistics]);

  const isLoading = typesLoading || ordersLoading;

  // Get active promotions
  const activeOrders = orders.filter(
    (o) => o.status === OrderStatus.ACTIVE || o.status === OrderStatus.PROCESSING
  );

  // Get recent orders
  const recentOrders = orders.slice(0, 5);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Promotions</h1>
              <p className="text-gray-600 mt-1">
                Boost your event visibility with targeted promotions
              </p>
            </div>
            <button
              onClick={onCreatePromotion}
              className="px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 transition-colors flex items-center"
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 4v16m8-8H4"
                />
              </svg>
              Create Promotion
            </button>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Statistics Cards */}
        {statistics && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <div className="flex items-center">
                <div className="p-3 bg-blue-100 rounded-lg">
                  <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                </div>
                <div className="ml-4">
                  <p className="text-sm text-gray-500">Total Orders</p>
                  <p className="text-2xl font-bold text-gray-900">{statistics.totalOrders}</p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <div className="flex items-center">
                <div className="p-3 bg-green-100 rounded-lg">
                  <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-4">
                  <p className="text-sm text-gray-500">Total Spent</p>
                  <p className="text-2xl font-bold text-gray-900">{statistics.totalSpent.toFixed(2)} RON</p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <div className="flex items-center">
                <div className="p-3 bg-yellow-100 rounded-lg">
                  <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                </div>
                <div className="ml-4">
                  <p className="text-sm text-gray-500">Active Promotions</p>
                  <p className="text-2xl font-bold text-gray-900">{statistics.activePromotions}</p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <div className="flex items-center">
                <div className="p-3 bg-purple-100 rounded-lg">
                  <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-4">
                  <p className="text-sm text-gray-500">Completed</p>
                  <p className="text-2xl font-bold text-gray-900">{statistics.completedPromotions}</p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Active Promotions */}
        {activeOrders.length > 0 && (
          <div className="mb-8">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Active Promotions</h2>
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
              <div className="divide-y divide-gray-200">
                {activeOrders.map((order) => (
                  <div
                    key={order.id}
                    className="p-4 hover:bg-gray-50 cursor-pointer"
                    onClick={() => onViewOrder(order.id)}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="font-medium text-gray-900">
                          Order #{order.orderNumber}
                        </div>
                        <div className="text-sm text-gray-500">
                          {order.items?.length || 0} promotion(s)
                        </div>
                      </div>
                      <div className="flex items-center">
                        <span
                          className={`px-3 py-1 rounded-full text-sm font-medium ${
                            order.status === OrderStatus.ACTIVE
                              ? 'bg-green-100 text-green-800'
                              : 'bg-blue-100 text-blue-800'
                          }`}
                        >
                          {order.statusDisplayName}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Available Promotion Types */}
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-gray-900">Available Promotions</h2>
            <a href="#" className="text-primary-600 hover:text-primary-700 text-sm font-medium">
              View all
            </a>
          </div>

          {isLoading ? (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="bg-white rounded-lg border border-gray-200 p-6 animate-pulse">
                  <div className="flex items-start gap-4 mb-4">
                    <div className="w-12 h-12 bg-gray-200 rounded-lg"></div>
                    <div className="flex-1">
                      <div className="h-5 bg-gray-200 rounded w-2/3 mb-2"></div>
                      <div className="h-4 bg-gray-200 rounded w-1/3"></div>
                    </div>
                  </div>
                  <div className="h-4 bg-gray-200 rounded w-full mb-2"></div>
                  <div className="h-4 bg-gray-200 rounded w-3/4"></div>
                </div>
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {promotionTypes.map((type) => (
                <PromotionTypeCard
                  key={type.id}
                  promotionType={type}
                  isSelected={false}
                  onSelect={() => onCreatePromotion()}
                  onConfigure={() => onCreatePromotion()}
                />
              ))}
            </div>
          )}
        </div>

        {/* Recent Orders */}
        {recentOrders.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900">Recent Orders</h2>
              <a href="#" className="text-primary-600 hover:text-primary-700 text-sm font-medium">
                View all orders
              </a>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Order
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Items
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Total
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Date
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {recentOrders.map((order) => (
                    <tr key={order.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-medium text-gray-900">
                          #{order.orderNumber}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span
                          className={`px-2 py-1 text-xs font-medium rounded-full bg-${order.statusColor}-100 text-${order.statusColor}-800`}
                        >
                          {order.statusDisplayName}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {order.items?.length || 0} item(s)
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {order.totalAmount.toFixed(2)} {order.currency}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(order.createdAt).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <button
                          onClick={() => onViewOrder(order.id)}
                          className="text-primary-600 hover:text-primary-900 font-medium"
                        >
                          View
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Empty State */}
        {!isLoading && recentOrders.length === 0 && (
          <div className="bg-white rounded-lg border border-gray-200 p-12 text-center">
            <div className="text-6xl mb-4">🚀</div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">
              No promotions yet
            </h3>
            <p className="text-gray-500 mb-6">
              Start promoting your events to reach more potential attendees.
            </p>
            <button
              onClick={onCreatePromotion}
              className="px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 transition-colors"
            >
              Create Your First Promotion
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default PromotionsDashboard;
