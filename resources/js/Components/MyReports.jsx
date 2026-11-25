import {Link} from '@inertiajs/react';

export default function MyReports({reports, labels}) {
    return <section id="my-reports" className="discussion-panel galery-item-block-background w-5/6 mx-auto" style={{display: 'block'}}>
        <h2>{labels.mine}</h2>
        {reports.data.map(report => <details key={report.id} className="discussion-message-body">
            <summary>#{report.id} · {report.reported_login} · {labels.topics[report.topic]} · <strong>{labels.statuses[report.status]}</strong></summary>
            <p>{report.message}</p>
            {report.decision && <p><strong>{labels.decision}</strong> · {report.decision}</p>}
        </details>)}
        {reports.prev_page_url && <Link href={reports.prev_page_url}>←</Link>}
        {reports.next_page_url && <Link href={reports.next_page_url}>→</Link>}
    </section>;
}
