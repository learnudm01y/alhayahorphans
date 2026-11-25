using Alhayah.LocalData.Data;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalData.Migrations;

[DbContext(typeof(LocalStoreDbContext))]
[Migration("20261125000200_InitialLocalStoreSchema")]
public sealed partial class InitialLocalStoreSchema
{
    protected override void BuildTargetModel(ModelBuilder modelBuilder)
    {
        modelBuilder.HasAnnotation("ProductVersion", "10.0.0");
        LocalStoreDbContext.ConfigureModel(modelBuilder);
    }
}
