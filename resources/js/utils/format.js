export const rupiah = (v) => "Rp " + Number(v ?? 0).toLocaleString("id-ID");

export const fmtDate = (d) => (d ? new Date(d).toLocaleString("id-ID") : "-");

export const statusClass = (status) =>
    ({
        pending: "bg-gray-100 text-gray-700",
        paid: "bg-blue-100 text-blue-700",
        processed: "bg-indigo-100 text-indigo-700",
        ready_to_ship: "bg-amber-100 text-amber-700",
        ready_for_pickup: "bg-teal-100 text-teal-800 border border-teal-300",
        shipping: "bg-orange-100 text-orange-700",
        completed: "bg-green-100 text-green-700",
        cancelled: "bg-red-100 text-red-700",
    })[status] ?? "bg-gray-100 text-gray-700";
