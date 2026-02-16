export const paramsToUpdate = (obj1, obj2) => {
  const claves = Object.keys(obj1);
  const diferentes = [];

  for (const clave of claves) {
    if (obj1[clave] !== obj2[clave]) {
      diferentes.push(clave);
    }
  }
  return diferentes;
};