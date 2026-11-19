import React from 'react';
import Icon from "@/Components/Icon.jsx";

const SearchInput = ({
    classDivName, classInputName, iconSize, placeholderText, color = '#031945', iconName = 'search',
    type = 'search', onSubmit, ...props
}) => {
    const Container = onSubmit ? 'form' : 'div';
    return (
        <Container className={classDivName} onSubmit={onSubmit}>
            <input type={type} className={classInputName} placeholder={placeholderText} {...props}/>
            {onSubmit
                ? <button type="submit" className="search-submit" aria-label={placeholderText}>
                    <Icon name={iconName} size={iconSize} color={color} aria-hidden="true" />
                </button>
                : <Icon name={iconName} size={iconSize} color={color}
                        className="cursor-pointer position-absolute right-[5%] top-[25%]" />}
        </Container>
    );
};

export default SearchInput;
