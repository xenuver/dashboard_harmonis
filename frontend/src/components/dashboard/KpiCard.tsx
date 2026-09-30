import React from 'react';

export interface KpiCardProps {
  title: string;
  value: string | number;
  comparison?: {
    value: string;
    label?: string; // e.g., "vs last month", "target"
    trend?: 'up' | 'down' | 'neutral';
  };
  showComparison?: boolean;
  icon?: React.ReactNode;
  className?: string;
  isLoading?: boolean; // 1. Added loading state prop
}

export const KpiCard: React.FC<KpiCardProps> = function({
  title,
  value,
  icon,
  className = '',
  isLoading = false, // Default to false
}) {
  return (
    <div className={className}>
      {/* Header: Title & Optional Action/Icon */}
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
        <span>{title}</span>
        {icon && (<span>{icon}</span>)}
      </div>

      {/* Main KPI Value */}
      <div>
        {isLoading ? (
          // Skeleton for the main value
          <div className="h-8 w-24 bg-gray-200 animate-pulse rounded" style={{ height: '2rem', width: '6rem', backgroundColor: '#e5e7eb', borderRadius: '0.25rem', animation: 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite' }} />
        ) : (
          <span style={{ fontSize: '1.5rem', fontWeight: 'bold' }}>{value}</span>
        )}
      </div>
    </div>
  );
};

export default KpiCard;