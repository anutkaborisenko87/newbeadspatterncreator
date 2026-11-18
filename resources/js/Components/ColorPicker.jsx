import React, {useState} from 'react';
import Icon from "@/Components/Icon.jsx";
import {useDispatch, useSelector} from "react-redux";
import {setFillColor, setPenColor} from "@/store/gridSlice.js";

const ColorPicker = ({color, title}) => {
    const dispatch = useDispatch();
    const [showColorPicker, setShowColorPicker] = useState(false);
    const {penColor, tool, fillColor} = useSelector((state) => state.grid);
    const selectedColor = tool === "fill" ? fillColor : penColor;
    const handleColorClick = () => {
        setShowColorPicker(!showColorPicker);
    };

    const handleColorChange = (newColor) => {
        if (tool === "fill") {
            dispatch(setFillColor(newColor));
        } else {
            dispatch(setPenColor(newColor));
        }
        setShowColorPicker(false);
    };

    return (
        <div className={'position-relative'}>
            <Icon className={'cursor-pointer'} name={'palette'} color={color} size={25}
                  onClick={handleColorClick}
                  title={title}/>
            {showColorPicker && (
                <div style={{position: 'absolute', zIndex: 100}}>
                    <input type="color" value={selectedColor}
                           onChange={(e) => handleColorChange(e.target.value)}/>
                </div>
            )}

        </div>
    );
};

export default ColorPicker;
