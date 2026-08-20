import { type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from '@/components/error-boundary';
import { Toaster } from '@/components/ui/toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Route, Switch, useLocation, Router as WouterRouter } from 'wouter';
import { AuthProvider } from '@/contexts/AuthContext';
import { CartProvider } from '@/contexts/CartContext';
import { ForumProvider } from '@/contexts/ForumContext';
import { AppLayout } from '@/components/layout/AppLayout';

// Pages
import Home from '@/pages/Home';
import Catalog, { CatalogTheme } from '@/pages/Catalog';
import CatalogGroup from '@/pages/CatalogGroup';
import LotDetail from '@/pages/LotDetail';
import Auctions from '@/pages/Auctions';
import Exclusives from '@/pages/Exclusives';
import Liquidation from '@/pages/Liquidation';
import Stickers from '@/pages/Stickers';
import News from '@/pages/News';
import NewsDetail from '@/pages/NewsDetail';
import Cart from '@/pages/Cart';
import Profile from '@/pages/Profile';
import Login from '@/pages/Login';
import Register from '@/pages/Register';
import NotFound from '@/pages/not-found';

// Forum
import ForumIndex from '@/pages/forum/ForumIndex';
import ForumCategory from '@/pages/forum/ForumCategory';
import ForumThread from '@/pages/forum/ForumThread';

// Admin Pages
import AdminDashboard from '@/pages/admin/AdminDashboard';
import AdminUsers from '@/pages/admin/AdminUsers';
import AdminInvites from '@/pages/admin/AdminInvites';
import AdminLots from '@/pages/admin/AdminLots';

const queryClient = new QueryClient();

function RoutedErrorBoundary({ children }: { children: ReactNode }) {
  const [location] = useLocation();
  return <ErrorBoundary resetKey={location}>{children}</ErrorBoundary>;
}

function MainLayout({ children }: { children: ReactNode }) {
  return <AppLayout>{children}</AppLayout>;
}

function AppRoutes() {
  return (
    <RoutedErrorBoundary>
      <Switch>
        {/* Auth routes — no layout */}
        <Route path="/login" component={Login} />
        <Route path="/register/:token" component={Register} />

        {/* Main routes with layout */}
        <Route path="/">
          <MainLayout><Home /></MainLayout>
        </Route>
        <Route path="/catalog">
          <MainLayout><Catalog /></MainLayout>
        </Route>
        <Route path="/catalog/:themeId">
          <MainLayout><CatalogTheme /></MainLayout>
        </Route>
        <Route path="/catalog/:themeId/groups/:groupId">
          <MainLayout><CatalogGroup /></MainLayout>
        </Route>
        <Route path="/lots/:id">
          <MainLayout><LotDetail /></MainLayout>
        </Route>
        <Route path="/auctions">
          <MainLayout><Auctions /></MainLayout>
        </Route>
        <Route path="/exclusives">
          <MainLayout><Exclusives /></MainLayout>
        </Route>
        <Route path="/liquidation">
          <MainLayout><Liquidation /></MainLayout>
        </Route>
        <Route path="/stickers">
          <MainLayout><Stickers /></MainLayout>
        </Route>
        <Route path="/news">
          <MainLayout><News /></MainLayout>
        </Route>
        <Route path="/news/:id">
          <MainLayout><NewsDetail /></MainLayout>
        </Route>
        <Route path="/cart">
          <MainLayout><Cart /></MainLayout>
        </Route>
        <Route path="/profile">
          <MainLayout><Profile /></MainLayout>
        </Route>

        {/* Forum — more-specific routes first */}
        <Route path="/forum">
          <MainLayout><ForumIndex /></MainLayout>
        </Route>
        <Route path="/forum/thread/:threadId">
          <MainLayout><ForumThread /></MainLayout>
        </Route>
        <Route path="/forum/:categoryId">
          <MainLayout><ForumCategory /></MainLayout>
        </Route>

        {/* Admin routes */}
        <Route path="/admin" component={AdminDashboard} />
        <Route path="/admin/users" component={AdminUsers} />
        <Route path="/admin/invites" component={AdminInvites} />
        <Route path="/admin/lots" component={AdminLots} />

        <Route>
          <MainLayout><NotFound /></MainLayout>
        </Route>
      </Switch>
    </RoutedErrorBoundary>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <AuthProvider>
          <CartProvider>
            <ForumProvider>
              <WouterRouter>
                <AppRoutes />
              </WouterRouter>
            </ForumProvider>
          </CartProvider>
        </AuthProvider>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
