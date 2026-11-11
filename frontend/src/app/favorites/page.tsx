import { AppShell } from "@/components/app-shell";
import { FavoritesList } from "@/components/favorites-list";
import "@/app/public-detail.css";

export default function FavoritesPage() {
  return <AppShell title="Tutor yêu thích" mode="public"><FavoritesList /></AppShell>;
}
