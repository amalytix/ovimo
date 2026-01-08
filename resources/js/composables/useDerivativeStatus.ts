export type DerivativeStatus = 'NOT_STARTED' | 'DRAFT' | 'FINAL' | 'PUBLISHED' | 'NOT_PLANNED';

export interface StatusOption {
    value: DerivativeStatus;
    label: string;
    dotColor: string;
    bgColor: string;
}

export const derivativeStatusOptions: StatusOption[] = [
    {
        value: 'NOT_STARTED',
        label: 'Not Started',
        dotColor: 'bg-gray-400',
        bgColor: 'bg-gray-100 border-gray-300 dark:bg-gray-800 dark:border-gray-600',
    },
    {
        value: 'DRAFT',
        label: 'Draft',
        dotColor: 'bg-orange-500',
        bgColor: 'bg-orange-50 border-orange-300 dark:bg-orange-900/30 dark:border-orange-700',
    },
    {
        value: 'FINAL',
        label: 'Final',
        dotColor: 'bg-green-500',
        bgColor: 'bg-green-50 border-green-300 dark:bg-green-900/30 dark:border-green-700',
    },
    {
        value: 'PUBLISHED',
        label: 'Published',
        dotColor: 'bg-purple-500',
        bgColor: 'bg-purple-50 border-purple-300 dark:bg-purple-900/30 dark:border-purple-700',
    },
    {
        value: 'NOT_PLANNED',
        label: 'Not Planned',
        dotColor: 'bg-gray-300',
        bgColor: 'bg-gray-50 border-gray-200 dark:bg-gray-800/50 dark:border-gray-700',
    },
];

export const getStatusDotColor = (status: DerivativeStatus): string => {
    return derivativeStatusOptions.find(s => s.value === status)?.dotColor ?? 'bg-gray-400';
};

export const getStatusBgColor = (status: DerivativeStatus): string => {
    return derivativeStatusOptions.find(s => s.value === status)?.bgColor ?? derivativeStatusOptions[0].bgColor;
};

export const getStatusLabel = (status: DerivativeStatus): string => {
    return derivativeStatusOptions.find(s => s.value === status)?.label ?? status;
};
