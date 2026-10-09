import NavBranding from "./NavBranding";

export function TopbarLogout() {
  return (
    <header className="sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-2 border-b bg-background px-4">
      <div className="flex items-center gap-2">
        <NavBranding />
      </div>
    </header>
  );
}

export default TopbarLogout;