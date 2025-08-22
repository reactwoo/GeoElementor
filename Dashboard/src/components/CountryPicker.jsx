import React, { useEffect, useMemo, useState } from "react";
import apiFetch from "@wordpress/api-fetch";

export default function CountryPicker({ value = [], onChange }) {
    const [options, setOptions] = useState([]);
    const [q, setQ] = useState("");

    useEffect(() => {
        async function load() {
            try {
                const res = await apiFetch({ path: "/geo-elementor/v1/countries" });
                setOptions(res?.countries || []);
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error("Countries load failed", e);
            }
        }
        load();
    }, []);

    const filtered = useMemo(() => {
        const term = q.trim().toLowerCase();
        if (!term) return options;
        return options.filter(o => (o.name.toLowerCase().includes(term) || o.code.toLowerCase().includes(term)));
    }, [q, options]);

    const add = (code) => {
        if (!value.includes(code)) onChange([...value, code]);
    };
    const remove = (code) => onChange(value.filter(v => v !== code));

    return (
        <div>
            <label style={{ display: "block", fontWeight: 600, marginBottom: 6 }}>Countries</label>
            <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 8 }}>
                {value.map(code => (
                    <span key={code} className="geo-el-badge" style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
                        {code}
                        <button type="button" onClick={() => remove(code)} aria-label={`Remove ${code}`} style={{ border: "none", background: "none", cursor: "pointer" }}>×</button>
                    </span>
                ))}
                {value.length === 0 && <span className="geo-el-help">No countries selected.</span>}
            </div>
            <input className="geo-el-input" placeholder="Search country…" value={q} onChange={e => setQ(e.target.value)} />
            <div style={{ maxHeight: 180, overflow: "auto", border: "1px solid #e5e7eb", borderRadius: 8, marginTop: 8 }}>
                {filtered.map(o => (
                    <div key={o.code} style={{ display: "flex", justifyContent: "space-between", padding: "6px 10px", borderBottom: "1px solid #f3f4f6" }}>
                        <span>{o.name} <span style={{ color: "#6b7280" }}>({o.code})</span></span>
                        <button type="button" onClick={() => add(o.code)} className="geo-el-primary" style={{ padding: "4px 10px" }}>Add</button>
                    </div>
                ))}
                {filtered.length === 0 && <div style={{ padding: 10, color: "#6b7280" }}>No matches.</div>}
            </div>
        </div>
    );
}
