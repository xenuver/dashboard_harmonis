import { Command } from "lucide-react"; // Example icon/logo, or use an <img> tag

export default function Logo() {
  return (
    <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/60">
      <div className="container flex h-14 items-center justify-between">
        
        {/* Left Side: Logo + Text */}
        <div className="flex items-center gap-6">
            {/* Replace Icon with <img src="/logo.svg" className="h-6 w-6" alt="Logo" /> */}
            <Command className="h-6 w-6" />
            <span className="inline-block text-lg">A</span>

          {/* Optional: Left-aligned Navigation Links */}
          <nav className="hidden md:flex items-center gap-4 text-sm font-medium text-muted-foreground">
              Dashboard
          </nav>
        </div>

        {/* Right Side: Actions (Profile, Search, Theme Toggle, etc.) */}
        <div className="flex items-center gap-2">
          {/* Add right-aligned buttons or user menus here */}
        </div>

      </div>
    </header>
  );
}