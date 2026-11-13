import { FavoritesList } from "@/components/favorites/favorites-list";
import { AppShell } from "@/components/layout/app-shell";
import "@/styles/public-detail.css";

export default function FavoritesPage() {
  return <AppShell title="Tutor yêu thích" mode="public"><FavoritesList /></AppShell>;
}
