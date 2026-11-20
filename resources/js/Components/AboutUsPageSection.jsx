const AboutUsPageSection = ({data}) => {
    return (
        <div className={'about-main-block '}>
            <h2>{ data.title ?? '' }</h2>
           <section dangerouslySetInnerHTML={{ __html: data.main_text ?? '' }}></section>
            {data.rated_pictures?.length > 0 && (
                <div className="about-rated-previews">
                    {data.rated_pictures.map(({id, title, preview}) => (
                        <img key={id} src={preview} alt={title ?? ''} loading="lazy" />
                    ))}
                </div>
            )}

        </div>
    );
};

export default AboutUsPageSection;
