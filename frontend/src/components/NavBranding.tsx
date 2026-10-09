export default function Logo() {
  return (
    <div className="flex items-center gap-2 font-semibold">
      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold">
        HD
      </div>
      <span className="group-data-[collapsible=icon]:hidden">Harmonis Dashboard</span>
    </div>
  );
}
