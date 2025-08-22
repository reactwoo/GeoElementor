import React, { useMemo, useState } from "react";
import apiFetch from "@wordpress/api-fetch";

function Switch({ on, onToggle, disabled }) {
    return (
        <div
            className="geo-el-switch"
            data-on={on ? "true" : "false"}
            onClick={() => !disabled && onToggle?.(!on)}
            role="switch"
            aria-checked={on ? "true" : "false"}
            aria-disabled={disabled ? "true" : "false"}
            style={disabled ? { opacity: .6, pointerEvents: "none" } : {}}
        >
            <div className="geo-el-switch__dot" />
        </div>
    );
}

export default function ItemsTable({ items = [], filters, onStatusPatched }) {
    const [busyId, setBusyId] = useState(null);

    const rows = useMemo(() => {
        return items
            .filter(i => filters.type === 'All' || i.type === filters.type)
            .filter(i => filters.country === 'All' || i.countries.includes(filters.country))
            .filter(i => !filters.q || i.title.toLowerCase().includes(filters.q.toLowerCase()));
    }, [items, filters]);

    const toggleStatus = async (row) => {
        const next = row.status === "publish" ? "draft" : "publish";
        setBusyId(row.id);
        try {
            const res = await apiFetch({
                path: `/geo-elementor/v1/items/${row.id}/status`,
                method: "POST",
                data: { status: next }
            });
            onStatusPatched?.(res);
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error("Failed to patch status", e);
            alert("Failed to update status.");
        } finally {
            setBusyId(null);
        }
    };

    return (
        <div className="geo-el-card">
            <h3>Targeted Items</h3>
            <table className="geo-el-table">
                <thead>
                    <tr>
                        <th style={{ textAlign: 'left' }}>Title</th>
                        <th>Type</th>
                        <th>Countries</th>
                        <th>Status</th>
                        <th>Toggle</th>
                        <th>Last Modified</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map(row => (
                        <tr key={row.id}>
                            <td style={{ textAlign: 'left' }}>{row.title}</td>
                            <td>{row.type}</td>
                            <td>
                                {row.countries.map(c => (
                                    <span key={c} className="geo-el-badge" style={{ marginRight: 6 }}>{c}</span>
                                ))}
                            </td>
                            <td className={`geo-el-status--${row.status}`}>{row.status}</td>
                            <td>
                                <Switch on={row.status === "publish"} onToggle={() => toggleStatus(row)} disabled={busyId === row.id} />
                            </td>
                            <td>{row.modified}</td>
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={6} style={{ textAlign: 'center', padding: '16px' }}>
                                No items match your filters.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
