import {Link} from "@inertiajs/react";
import Icon from "@/Components/Icon.jsx";
import {useSelector} from "react-redux";

const Pagination = ({current_page, first_page_url, last_page, prev_page_url, next_page_url, last_page_url, links, preserveScroll}) => {
    const {mode} = useSelector((state) => state.themeMode);
    let color = '';
    switch (mode) {
        case 'positive':
            color = '#031945';
            break;
        case 'negative':
            color = '#8FA8DE';
            break;
        case 'dark':
            color = '#AEC2ED';
            break;
        default:
            color = '#1F51BD';
    }
    return (
        <div className="pagination-controls">
            {current_page > 2 &&
                <Link className={'pagination-prev-next-links'} href={first_page_url} preserveScroll={preserveScroll}><Icon name={'first_page'}
                                                                                           color={color} size={32}/></Link>}
            {prev_page_url &&
                <Link className={'pagination-prev-next-links'} href={prev_page_url} preserveScroll={preserveScroll}><Icon name={'prev_page'}
                                                                                          color={color} size={32}/></Link>}
            {links.slice(1, -1).map((link, index) => link.url
                ? <Link key={index} href={link.url} preserveScroll={preserveScroll} aria-current={link.active ? 'page' : undefined}
                        className={`pagination-links ${link.active ? 'active' : ''}`}>{link.label}</Link>
                : <span key={index} className="pagination-links">{link.label}</span>)}
            {next_page_url &&
                <Link className={'pagination-prev-next-links'} href={next_page_url} preserveScroll={preserveScroll}><Icon name={'prev_page'}
                                                                                          className={'rotate-180'}
                                                                                          color={color} size={32}/></Link>}
            {current_page < last_page - 2 &&
                <Link className={'pagination-prev-next-links'} href={last_page_url} preserveScroll={preserveScroll}><Icon name={'first_page'}
                                                                                          className={'rotate-180'}
                                                                                          color={color} size={32}/></Link>}


        </div>
    );
};

export default Pagination;
