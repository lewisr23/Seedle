import { useEffect, useState } from 'react';
import { api } from '../api/client';

const STATUS_COLORS = {
  pending: '#8a6100',
  processing: '#c98a2c',
  completed: '#2d5a3f',
  cancelled: '#b3261e',
};

export default function Orders() {
  const [orders, setOrders] = useState(null);

  const load = () => api('/orders').then((res) => setOrders(res.data));

  useEffect(() => {
    load();
    // Orders move from "processing" to "completed" asynchronously in the
    // background, so poll briefly to reflect that without a manual refresh.
    const interval = setInterval(load, 4000);
    return () => clearInterval(interval);
  }, []);

  if (orders === null) return <div className="page container loading">Loading orders...</div>;

  return (
    <div className="page container">
      <h1 className="page-title">Your orders</h1>

      {orders.length === 0 && <div className="empty-state">No orders yet.</div>}

      {orders.map((order) => (
        <div className="card" key={order.id} style={{ padding: 16, marginBottom: 12 }}>
          <div className="flex-between">
            <strong>Order #{order.id}</strong>
            <span style={{ color: STATUS_COLORS[order.status], fontWeight: 600, textTransform: 'capitalize' }}>
              {order.status}
            </span>
          </div>
          <p style={{ fontSize: 13, color: '#4f5a52', margin: '4px 0 10px' }}>
            {new Date(order.created_at).toLocaleString()} · £{order.total_pounds.toFixed(2)}
          </p>
          {order.items?.map((item) => (
            <div key={item.id} style={{ fontSize: 14 }}>
              {item.quantity} × {item.product?.title}
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}
