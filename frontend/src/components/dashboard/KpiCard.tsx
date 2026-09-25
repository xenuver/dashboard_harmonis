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
}


export const KpiCard: React.FC<KpiCardProps> = function({
  title,
  value,
  comparison,
  showComparison = false,
  icon,
  className = '',
}) {
  const getTrendIcon = (trend?: 'up' | 'down' | 'neutral') => {
    if (trend === 'up') return '↑';
    if (trend === 'down') return '↓';
    return null;
  };

  return (
    <div className={className}>
      {/* Header: Title & Optional Action/Icon */}
      <div>
        <span>{title}</span>
        {icon && (<span>{icon}</span>)}
      </div>

      {/* Main KPI Value */}
      <div>
        <span>{value}</span>
      </div>

      {/* Optional & Hideable Comparison Row */}
      {showComparison && comparison && (
        <div>
          <span>
            {getTrendIcon(comparison.trend)}
            <span>{comparison.value}</span>
          </span>

          {comparison.label && (
            <span>{comparison.label}</span>
          )}
        </div>
      )}
    </div>
  );
};

export default KpiCard