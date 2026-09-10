import { Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import ProtectedRoute from './components/ProtectedRoute';
import Home from './pages/Home';
import ProductDetail from './pages/ProductDetail';
import Cart from './pages/Cart';
import Orders from './pages/Orders';
import Login from './pages/Login';
import Register from './pages/Register';
import Feed from './pages/Feed';
import Sell from './pages/Sell';
import GardenPlanner from './pages/GardenPlanner';
import GardenBedDetail from './pages/GardenBedDetail';
import Profile from './pages/Profile';

export default function App() {
  return (
    <>
      <Navbar />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/products/:id" element={<ProductDetail />} />
        <Route path="/cart" element={<Cart />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/feed" element={<Feed />} />
        <Route path="/u/:username" element={<Profile />} />

        <Route
          path="/orders"
          element={
            <ProtectedRoute>
              <Orders />
            </ProtectedRoute>
          }
        />
        <Route
          path="/sell"
          element={
            <ProtectedRoute>
              <Sell />
            </ProtectedRoute>
          }
        />
        <Route
          path="/garden"
          element={
            <ProtectedRoute>
              <GardenPlanner />
            </ProtectedRoute>
          }
        />
        <Route
          path="/garden/:id"
          element={
            <ProtectedRoute>
              <GardenBedDetail />
            </ProtectedRoute>
          }
        />
      </Routes>
    </>
  );
}
