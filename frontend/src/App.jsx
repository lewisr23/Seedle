import { Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import ProtectedRoute from './components/ProtectedRoute';
import ErrorBoundary from './components/ErrorBoundary';
import Home from './pages/Home';
import ProductDetail from './pages/ProductDetail';
import Cart from './pages/Cart';
import Orders from './pages/Orders';
import Login from './pages/Login';
import Messages from './pages/Messages';
import Register from './pages/Register';
import Saved from './pages/Saved';
import Settings from './pages/Settings';
import Wanted from './pages/Wanted';
import Conversation from './pages/Conversation';
import Feed from './pages/Feed';
import PostDetail from './pages/PostDetail';
import Sell from './pages/Sell';
import Dashboard from './pages/Dashboard';
import GardenPlanner from './pages/GardenPlanner';
import GardenBedDetail from './pages/GardenBedDetail';
import Calendar from './pages/Calendar';
import Harvests from './pages/Harvests';
import Profile from './pages/Profile';
import Guides from './pages/Guides';
import GuideDetail from './pages/GuideDetail';
import Plants from './pages/Plants';
import PlantDetail from './pages/PlantDetail';

export default function App() {
  return (
    <>
      <Navbar />
      <ErrorBoundary>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/products/:id" element={<ProductDetail />} />
          <Route path="/cart" element={<Cart />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/feed" element={<Feed />} />
          <Route path="/posts/:id" element={<PostDetail />} />
          <Route path="/guides" element={<Guides />} />
          <Route path="/guides/:slug" element={<GuideDetail />} />
          <Route path="/plants" element={<Plants />} />
          <Route path="/calendar" element={<Calendar />} />
          <Route path="/plants/:id" element={<PlantDetail />} />
          <Route path="/wanted" element={<Wanted />} />
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
            path="/settings"
            element={
              <ProtectedRoute>
                <Settings />
              </ProtectedRoute>
            }
          />
          <Route
            path="/saved"
            element={
              <ProtectedRoute>
                <Saved />
              </ProtectedRoute>
            }
          />
          <Route
            path="/messages"
            element={
              <ProtectedRoute>
                <Messages />
              </ProtectedRoute>
            }
          />
          <Route
            path="/messages/:id"
            element={
              <ProtectedRoute>
                <Conversation />
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
            path="/dashboard"
            element={
              <ProtectedRoute>
                <Dashboard />
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
          <Route
            path="/harvests"
            element={
              <ProtectedRoute>
                <Harvests />
              </ProtectedRoute>
            }
          />
        </Routes>
      </ErrorBoundary>
      <Footer />
    </>
  );
}
