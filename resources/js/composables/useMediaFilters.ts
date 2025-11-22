import { computed, reactive } from 'vue';

export type MediaFilters = {
    search: string;
    tag_ids: number[];
    file_type: 'all' | 'images' | 'pdfs';
    date_from: string | null;
    date_to: string | null;
    sort_by: 'uploaded_at' | 'filename';
    sort_dir: 'asc' | 'desc';
};

const defaultFilters = (): MediaFilters => ({
    search: '',
    tag_ids: [],
    file_type: 'all',
    date_from: null,
    date_to: null,
    sort_by: 'uploaded_at',
    sort_dir: 'desc',
});

export const useMediaFilters = (initial: Partial<MediaFilters> = {}) => {
    const filters = reactive<MediaFilters>({
        ...defaultFilters(),
        ...initial,
        tag_ids: [...(initial.tag_ids ?? [])],
        sort_by: (initial.sort_by as MediaFilters['sort_by']) || 'uploaded_at',
        sort_dir: (initial.sort_dir as MediaFilters['sort_dir']) || 'desc',
        file_type: (initial.file_type as MediaFilters['file_type']) || 'all',
        date_from: (initial.date_from as string | null) ?? null,
        date_to: (initial.date_to as string | null) ?? null,
        search: initial.search ?? '',
    });

    const normalizedFilters = computed(() => ({
        search: filters.search || undefined,
        tag_ids: filters.tag_ids.length > 0 ? filters.tag_ids : undefined,
        file_type: filters.file_type !== 'all' ? filters.file_type : undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        sort_by: filters.sort_by,
        sort_dir: filters.sort_dir,
    }));

    const resetFilters = () => {
        Object.assign(filters, defaultFilters());
    };

    return {
        filters,
        normalizedFilters,
        resetFilters,
    };
};
