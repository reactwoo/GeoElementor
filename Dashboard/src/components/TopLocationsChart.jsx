import React from "react";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

export default function TopLocationsChart({ data }) {
    const chartData = (data || []).map(row => ({
        name: row.code === 'OTHER' ? 'Other' : row.code,
        percent: row.percent
    }));

    return (
        <div className="geo-el-card geo-el-grid-4">
            <h3>Top Traffic by Location</h3>
            <div style={{ width: "100%", height: 220 }}>
                <ResponsiveContainer>
                    <BarChart data={chartData}>
                        <XAxis dataKey="name" />
                        <YAxis unit="%" />
                        <Tooltip />
                        <Bar dataKey="percent" />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
