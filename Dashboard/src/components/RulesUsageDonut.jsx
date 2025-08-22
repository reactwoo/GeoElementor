import React from "react";
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer, Legend } from "recharts";

export default function RulesUsageDonut({ data }) {
    const total = (data || []).reduce((acc, r) => acc + r.count, 0);
    const chartData = (data || []).map(r => ({ name: r.type, value: r.count }));

    return (
        <div className="geo-el-card geo-el-grid-4">
            <h3>Geo Rules Usage</h3>
            <div style={{ width: "100%", height: 220 }}>
                <ResponsiveContainer>
                    <PieChart>
                        <Pie
                            data={chartData}
                            dataKey="value"
                            nameKey="name"
                            innerRadius={50}
                            outerRadius={80}
                        >
                            {chartData.map((entry, index) => (
                                <Cell key={`cell-${index}`} />
                            ))}
                        </Pie>
                        <Tooltip />
                        <Legend />
                    </PieChart>
                </ResponsiveContainer>
                <div style={{ marginTop: 6, fontSize: 12 }}>
                    Total active rules: <strong>{total}</strong>
                </div>
            </div>
        </div>
    );
}
