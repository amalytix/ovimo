export interface MediaTag {
    id: number;
    name: string;
}

export interface MediaItem {
    id: number;
    filename: string;
    mime_type: string;
    file_size: number;
    created_at: string | null;
    temporary_url?: string;
    download_url?: string;
    metadata?: Record<string, unknown> | null;
    tags: MediaTag[];
}
