import { api } from './client';

export interface PsgcItem {
    code: string;
    name: string;
}

interface PsgcResponse {
    data: PsgcItem[];
    recordsTotal: number;
}

export async function getRegions(): Promise<PsgcItem[]> {
    const { data } = await api.get<PsgcResponse>('/psgc/regions', { params: { limit: 100, order_by: 'name' } });
    return data.data ?? [];
}

export async function getProvinces(regionCode: string): Promise<PsgcItem[]> {
    const { data } = await api.get<PsgcResponse>('/psgc/provinces', { params: { region_code: regionCode, limit: 100, order_by: 'name' } });
    return data.data ?? [];
}

export async function getCities(provinceCode: string): Promise<PsgcItem[]> {
    const { data } = await api.get<PsgcResponse>('/psgc/cities', { params: { province_code: provinceCode, limit: 100, order_by: 'name' } });
    return data.data ?? [];
}

export async function getBarangays(cityCode: string): Promise<PsgcItem[]> {
    const { data } = await api.get<PsgcResponse>('/psgc/barangays', { params: { city_code: cityCode, limit: 100, order_by: 'name' } });
    return data.data ?? [];
}
