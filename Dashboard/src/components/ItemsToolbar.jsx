import React from "react";

export default function ItemsToolbar({ types = [], countries = [], value, onChange, onCreate }) {
    const onType = (e) => onChange({ ...value, type: e.target.value });
    const onCountry = (e) => onChange({ ...value, country: e.target.value });
    const onSearch = (e) => onChange({ ...value, q: e.target.value });

    return (
        <div className="geo-el-card">
            <div className="geo-el-headerbar">
                <div className="geo-el-toolbar">
                    <select value={value.type} onChange={onType} className="geo-el-select">
                        {types.map(t => <option key={t} value={t}>{t}</option>)}
                    </select>
                    <select value={value.country} onChange={onCountry} className="geo-el-select">
                        {countries.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                    <input
                        type="search"
                        className="geo-el-input"
                        placeholder="Search title…"
                        value={value.q}
                        onChange={onSearch}
                        style={{ minWidth: 220 }}
                    />
                </div>
                <button className="geo-el-primary" onClick={onCreate}>+ Create Geo Rule</button>
            </div>
        </div>
    );
}
