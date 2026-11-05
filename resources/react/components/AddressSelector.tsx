import { useEffect, useId, useState } from 'react';
import { getRegions, getProvinces, getCities, getBarangays, type PsgcItem } from '../api/psgc';

export interface AddressValues {
    region: string;
    province: string;
    city: string;
    barangay: string;
    street: string;
    zip_code: string;
}

interface AddressSelectorProps {
    value: AddressValues;
    onChange: (v: AddressValues) => void;
    errors?: Record<string, string>;
    selectClassName?: string;
    inputClassName?: string;
}

interface SelectedItem {
    code: string;
    name: string;
}

const DEFAULT_SELECT_CLASS = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500';
const DEFAULT_INPUT_CLASS = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500';

export default function AddressSelector({ value, onChange, errors = {}, selectClassName, inputClassName }: AddressSelectorProps) {
    const idPrefix = useId();
    const selectCls = selectClassName ?? DEFAULT_SELECT_CLASS;
    const inputCls = inputClassName ?? DEFAULT_INPUT_CLASS;

    const [regions, setRegions] = useState<PsgcItem[]>([]);
    const [provinces, setProvinces] = useState<PsgcItem[]>([]);
    const [cities, setCities] = useState<PsgcItem[]>([]);
    const [barangays, setBarangays] = useState<PsgcItem[]>([]);
    const [regionItem, setRegionItem] = useState<SelectedItem | null>(null);
    const [provinceItem, setProvinceItem] = useState<SelectedItem | null>(null);
    const [cityItem, setCityItem] = useState<SelectedItem | null>(null);
    const [barangayItem, setBarangayItem] = useState<SelectedItem | null>(null);
    const [hasNoProvince, setHasNoProvince] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        let active = true;
        setLoading(true);
        getRegions()
            .then(async (fetchedRegions) => {
                if (!active) return;
                setRegions(fetchedRegions);

                if (value.region) {
                    const matchedRegion = fetchedRegions.find(r => r.name === value.region);
                    if (matchedRegion) {
                        setRegionItem({ code: matchedRegion.code, name: matchedRegion.name });
                        const provs = await getProvinces(matchedRegion.code);
                        if (!active) return;

                        if (provs.length === 0) {
                            setHasNoProvince(true);
                            setProvinces([]);
                            const cits = await getCities(undefined, matchedRegion.code);
                            if (!active) return;
                            setCities(cits);
                            if (value.city) {
                                const matchedCity = cits.find(c => c.name === value.city);
                                if (matchedCity) {
                                    setCityItem({ code: matchedCity.code, name: matchedCity.name });
                                    const brgys = await getBarangays(matchedCity.code);
                                    if (!active) return;
                                    setBarangays(brgys);
                                    if (value.barangay) {
                                        const matchedBrgy = brgys.find(b => b.name === value.barangay);
                                        if (matchedBrgy) {
                                            setBarangayItem({ code: matchedBrgy.code, name: matchedBrgy.name });
                                        }
                                    }
                                }
                            }
                        } else {
                            setHasNoProvince(false);
                            setProvinces(provs);
                            if (value.province) {
                                const matchedProv = provs.find(p => p.name === value.province);
                                if (matchedProv) {
                                    setProvinceItem({ code: matchedProv.code, name: matchedProv.name });
                                    const cits = await getCities(matchedProv.code);
                                    if (!active) return;
                                    setCities(cits);
                                    if (value.city) {
                                        const matchedCity = cits.find(c => c.name === value.city);
                                        if (matchedCity) {
                                            setCityItem({ code: matchedCity.code, name: matchedCity.name });
                                            const brgys = await getBarangays(matchedCity.code);
                                            if (!active) return;
                                            setBarangays(brgys);
                                            if (value.barangay) {
                                                const matchedBrgy = brgys.find(b => b.name === value.barangay);
                                                if (matchedBrgy) {
                                                    setBarangayItem({ code: matchedBrgy.code, name: matchedBrgy.name });
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            })
            .catch(() => setRegions([]))
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, []);

    const selectRegion = async (item: SelectedItem | null) => {
        setRegionItem(item);
        setProvinceItem(null);
        setCityItem(null);
        setBarangayItem(null);
        setProvinces([]);
        setCities([]);
        setBarangays([]);
        setHasNoProvince(false);
        onChange({ ...value, region: item?.name ?? '', province: '', city: '', barangay: '' });

        if (item) {
            setLoading(true);
            try {
                const provs = await getProvinces(item.code);
                if (provs.length === 0) {
                    setHasNoProvince(true);
                    setProvinces([]);
                    const cits = await getCities(undefined, item.code);
                    setCities(cits);
                } else {
                    setHasNoProvince(false);
                    setProvinces(provs);
                }
            } catch {
                setProvinces([]);
            } finally {
                setLoading(false);
            }
        }
    };

    const selectProvince = async (item: SelectedItem | null) => {
        setProvinceItem(item);
        setCityItem(null);
        setBarangayItem(null);
        setCities([]);
        setBarangays([]);
        onChange({ ...value, province: item?.name ?? '', city: '', barangay: '' });

        if (item) {
            setLoading(true);
            try {
                setCities(await getCities(item.code));
            } catch {
                setCities([]);
            } finally {
                setLoading(false);
            }
        }
    };

    const selectCity = async (item: SelectedItem | null) => {
        setCityItem(item);
        setBarangayItem(null);
        setBarangays([]);
        onChange({ ...value, city: item?.name ?? '', barangay: '' });

        if (item) {
            setLoading(true);
            try {
                setBarangays(await getBarangays(item.code));
            } catch {
                setBarangays([]);
            } finally {
                setLoading(false);
            }
        }
    };

    const selectBarangay = (item: SelectedItem | null) => {
        setBarangayItem(item);
        onChange({ ...value, barangay: item?.name ?? '' });
    };

    const renderSelect = (
        label: string,
        items: PsgcItem[],
        selected: SelectedItem | null,
        onSelect: (item: SelectedItem | null) => void,
        field: string,
        placeholder: string,
        disabled: boolean,
        required = true,
    ) => (
        <div>
            <label htmlFor={`${idPrefix}-${field}`} className="block text-sm font-medium text-slate-700 mb-2">
                {label}{required && <span className="text-red-500">*</span>}
            </label>
            <select
                id={`${idPrefix}-${field}`}
                aria-invalid={Boolean(errors[field])}
                aria-describedby={errors[field] ? `${idPrefix}-${field}-error` : undefined}
                value={selected ? selected.name : ''}
                onChange={e => {
                    const match = items.find(i => i.name === e.target.value) ?? null;
                    onSelect(match ? { code: match.code, name: match.name } : null);
                }}
                disabled={disabled}
                className={`${selectCls} ${disabled ? 'opacity-60 bg-slate-50' : ''} ${(errors as any)[field] ? 'border-red-400' : ''}`}
            >
                <option value="">{placeholder}</option>
                {items.map(i => (
                    <option key={i.code} value={i.name}>{i.name}</option>
                ))}
            </select>
            {errors[field] && <p id={`${idPrefix}-${field}-error`} role="alert" className="mt-1 text-xs text-red-500">{errors[field]}</p>}
        </div>
    );

    const renderInput = (field: string, label: string, placeholder: string) => (
        <div>
            <label htmlFor={`${idPrefix}-${field}`} className="block text-sm font-medium text-slate-700 mb-2">{label}</label>
            <input id={`${idPrefix}-${field}`} type="text" value={(value as any)[field] || ''} onChange={e => onChange({ ...value, [field]: e.target.value })} aria-invalid={Boolean(errors[field])} aria-describedby={errors[field] ? `${idPrefix}-${field}-error` : undefined} placeholder={placeholder} className={`${inputCls} ${errors[field] ? 'border-red-400' : ''}`} />
            {errors[field] && <p id={`${idPrefix}-${field}-error`} role="alert" className="mt-1 text-xs text-red-500">{errors[field]}</p>}
        </div>
    );

    const provinceDisabled = !regionItem || hasNoProvince;
    const provincePlaceholder = hasNoProvince
        ? 'N/A (Not applicable for this region)'
        : regionItem
        ? 'Select Province'
        : 'Select Region first';

    const cityDisabled = hasNoProvince ? !regionItem : !provinceItem;
    const cityPlaceholder = hasNoProvince
        ? (regionItem ? 'Select City/Municipality' : 'Select Region first')
        : (provinceItem ? 'Select City/Municipality' : 'Select Province first');

    const barangayDisabled = !cityItem;
    const barangayPlaceholder = cityItem ? 'Select Barangay' : 'Select City first';

    return (
        <div className="space-y-4">
            {loading && <p className="text-xs text-slate-500"><i className="fas fa-spinner fa-spin mr-1"></i>Loading locations...</p>}
            <div className="grid md:grid-cols-2 gap-4">
                {renderSelect('Region', regions, regionItem, selectRegion, 'region', 'Select Region', false, true)}
                {renderSelect('Province', provinces, provinceItem, selectProvince, 'province', provincePlaceholder, provinceDisabled, !hasNoProvince)}
                {renderSelect('City/Municipality', cities, cityItem, selectCity, 'city', cityPlaceholder, cityDisabled, true)}
                {renderSelect('Barangay', barangays, barangayItem, selectBarangay, 'barangay', barangayPlaceholder, barangayDisabled, true)}
            </div>
            <div className="grid md:grid-cols-2 gap-4">
                {renderInput('street', 'Street Address', 'House No., Street Name')}
                {renderInput('zip_code', 'Postal/ZIP Code', 'ZIP Code')}
            </div>
        </div>
    );
}
