import React from "react";
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend } from "recharts";

function toSeries(labels, seriesMap, key) {
    return labels.map((label, idx) => ({
        label,
        GB: seriesMap.GB?.[key]?.[idx] ?? 0,
        US: seriesMap.US?.[key]?.[idx] ?? 0
    }));
}

export default function EngagementByCountry({ labels = [], byCountry = {} }) {
    const views = toSeries(labels, byCountry, 'views');
    const conversions = toSeries(labels, byCountry, 'conversions');

    const merged = labels.map((l, i) => ({
        label: l,
        GB_views: views[i].GB,
        US_views: views[i].US,
        GB_conv: conversions[i].GB,
        US_conv: conversions[i].US
    }));

    return (
        <div className="geo-el-card geo-el-grid-8">
            <h3>Engagement (Views vs Conversions)</h3>
            <div style={{ width: "100%", height: 240 }}>
                <ResponsiveContainer>
                    <LineChart data={merged}>
                        <XAxis dataKey="label" />
                        <YAxis />
                        <Tooltip />
                        <Legend />
                        <Line type="monotone" dataKey="GB_views" name="GB Views" strokeDasharray="" />
                        <Line type="monotone" dataKey="US_views" name="US Views" strokeDasharray="" />
                        <Line type="monotone" dataKey="GB_conv" name="GB Conversions" strokeDasharray="5 5" />
                        <Line type="monotone" dataKey="US_conv" name="US Conversions" strokeDasharray="5 5" />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
