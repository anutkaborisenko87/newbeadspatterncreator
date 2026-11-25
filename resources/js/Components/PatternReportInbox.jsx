import {Link} from '@inertiajs/react';

export default function PatternReportInbox({reports, labels}) {
    return <section id="pattern-reports" className="discussion-compose pattern-report-inbox galery-item-block-background w-5/6 mx-auto">
        <h2>{labels.heading}</h2>
        {reports.data.map(item => <p key={item.id}>
            {item.notice} · <strong>{item.patternUrl
                ? <Link href={item.patternUrl} className="underline">{item.title}</Link>
                : item.title}</strong> · {item.topic} · {item.status}
        </p>)}
        {reports.prev_page_url && <Link href={reports.prev_page_url}>←</Link>}
        {reports.next_page_url && <Link href={reports.next_page_url}>→</Link>}
    </section>;
}
