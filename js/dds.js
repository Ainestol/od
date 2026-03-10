async function loadCrest(id, element){

const res = await fetch(`/api/crest.php?id=${id}`);
const buffer = await res.arrayBuffer();

const dds = new DDS(buffer);

const canvas = document.createElement("canvas");
canvas.width = dds.width;
canvas.height = dds.height;

const ctx = canvas.getContext("2d");
const imageData = ctx.createImageData(dds.width, dds.height);

dds.decode(imageData.data);

ctx.putImageData(imageData,0,0);

element.appendChild(canvas);

}