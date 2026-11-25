using Alhayah.LocalData.Data;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalData.Migrations;

[DbContext(typeof(LocalStoreDbContext))]
public sealed class LocalStoreDbContextModelSnapshot : ModelSnapshot
{
    protected override void BuildModel(ModelBuilder modelBuilder)
    {
        modelBuilder.HasAnnotation("ProductVersion", "10.0.0");
        LocalStoreDbContext.ConfigureModel(modelBuilder);
    }
}
